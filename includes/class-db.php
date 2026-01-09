<?php

/**
 * Класс для работы с базой данных плагина
 */

class RDS_AIE_DB
{

	/**
	 * Создание таблиц БД
	 */
	public function create_tables()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix = $wpdb->prefix . 'rds_aie_';

		// Таблица моделей
		$sql1 = "CREATE TABLE IF NOT EXISTS {$table_prefix}models (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        base_url VARCHAR(255) NOT NULL,
        model_name VARCHAR(255) NOT NULL,
        api_key TEXT NOT NULL,
        max_tokens INT DEFAULT 4096,
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY is_default (is_default)
    ) $charset_collate;";

		// Таблица ассистентов
		$sql2 = "CREATE TABLE IF NOT EXISTS {$table_prefix}assistants (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        system_prompt TEXT NOT NULL,
        default_model_id BIGINT(20) UNSIGNED,
        max_tokens INT DEFAULT 1000,
        temperature DECIMAL(3,2) DEFAULT 0.7,
        history_enabled TINYINT(1) DEFAULT 1,
        history_messages_count INT DEFAULT 10,
        knowledge_base_enabled TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY default_model_id (default_model_id),
        FOREIGN KEY (default_model_id) REFERENCES {$table_prefix}models(id) ON DELETE SET NULL
    ) $charset_collate;";

		// ТАБЛИЦА ИСТОРИИ ДИАЛОГОВ (НОВАЯ)
		$sql3 = "CREATE TABLE IF NOT EXISTS {$table_prefix}conversations (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        plugin_id VARCHAR(100) DEFAULT 'default',
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        session_id VARCHAR(100) NOT NULL,
        assistant_id BIGINT(20) UNSIGNED,
        model_id BIGINT(20) UNSIGNED,
        role ENUM('system', 'user', 'assistant') NOT NULL,
        content TEXT NOT NULL,
        tokens INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY plugin_id (plugin_id),
        KEY user_id (user_id),
        KEY assistant_id (assistant_id),
        KEY created_at (created_at),
        INDEX session_created (session_id, created_at)
    ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql1);
		dbDelta($sql2);
		dbDelta($sql3);
	}

	/**
	 * Удаление таблиц БД
	 */
	public function drop_tables()
	{
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'rds_aie_';
		$tables = [
			'conversations',  // НОВАЯ ТАБЛИЦА
			'assistants',
			'models'
		];

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$table_prefix}{$table}");
		}
	}

	/**
	 * Сохранение сообщения в историю
	 */
	public function save_conversation_message($data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		$defaults = [
			'plugin_id' => 'default',
			'user_id' => get_current_user_id(),
			'session_id' => $this->generate_session_id(),
			'assistant_id' => null,
			'model_id' => null,
			'role' => 'user',
			'content' => '',
			'tokens' => 0
		];

		$data = wp_parse_args($data, $defaults);

		return $wpdb->insert($table_name, $data);
	}

	/**
	 * Получение истории диалога
	 */
	public function get_conversation_history($session_id, $limit = 10)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		return $wpdb->get_results($wpdb->prepare(
			"SELECT role, content, created_at 
         FROM {$table_name} 
         WHERE session_id = %s 
         ORDER BY created_at ASC 
         LIMIT %d",
			$session_id,
			$limit
		));
	}

	/**
	 * Получение истории по параметрам
	 */
	public function get_conversation_by_params($params = [], $limit = 10)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		$where = [];
		$values = [];

		if (!empty($params['session_id'])) {
			$where[] = 'session_id = %s';
			$values[] = $params['session_id'];
		}

		if (!empty($params['plugin_id'])) {
			$where[] = 'plugin_id = %s';
			$values[] = $params['plugin_id'];
		}

		if (!empty($params['user_id'])) {
			$where[] = 'user_id = %d';
			$values[] = $params['user_id'];
		}

		if (!empty($params['assistant_id'])) {
			$where[] = 'assistant_id = %d';
			$values[] = $params['assistant_id'];
		}

		$where_clause = '';
		if (!empty($where)) {
			$where_clause = 'WHERE ' . implode(' AND ', $where);
		}

		$query = "SELECT role, content, created_at 
              FROM {$table_name} 
              {$where_clause} 
              ORDER BY created_at ASC 
              LIMIT %d";

		$values[] = $limit;

		if (!empty($values)) {
			return $wpdb->get_results($wpdb->prepare($query, $values));
		}

		return [];
	}

	/**
	 * Очистка старых записей истории
	 */
	public function cleanup_old_conversations($days = 7)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		$date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

		return $wpdb->query($wpdb->prepare(
			"DELETE FROM {$table_name} WHERE created_at < %s",
			$date
		));
	}

	/**
	 * Удаление истории по session_id
	 */
	public function delete_conversation_session($session_id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		return $wpdb->delete($table_name, ['session_id' => $session_id]);
	}

	/**
	 * Генерация session_id
	 */
	private function generate_session_id()
	{
		// Если есть текущая сессия - используем её
		if (session_id()) {
			return session_id();
		}

		// Иначе генерируем уникальный ID
		return wp_generate_uuid4();
	}

	/**
	 * Получение всех моделей
	 */
	public function get_models()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';

		return $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY is_default DESC, name ASC"
		);
	}

	/**
	 * Получение модели по ID
	 */
	public function get_model($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';

		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id)
		);
	}

	/**
	 * Сохранение модели
	 */
	public function save_model($data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';

		// Если есть ID - обновляем, иначе - вставляем
		if (!empty($data['id'])) {
			$wpdb->update($table_name, $data, ['id' => $data['id']]);
			return $data['id'];
		} else {
			$wpdb->insert($table_name, $data);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Удаление модели
	 */
	public function delete_model($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';

		return $wpdb->delete($table_name, ['id' => $id]);
	}

	/**
	 * Получение всех ассистентов
	 */
	public function get_assistants()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_assistants';
		$models_table = $wpdb->prefix . 'rds_aie_models';

		return $wpdb->get_results("
            SELECT a.*, m.name as model_name 
            FROM {$table_name} a 
            LEFT JOIN {$models_table} m ON a.default_model_id = m.id 
            ORDER BY a.name ASC
        ");
	}

	/**
	 * Получение ассистента по ID
	 */
	public function get_assistant($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_assistants';
		$models_table = $wpdb->prefix . 'rds_aie_models';

		return $wpdb->get_row($wpdb->prepare("
            SELECT a.*, m.name as model_name, m.base_url, m.model_name as ai_model, m.api_key 
            FROM {$table_name} a 
            LEFT JOIN {$models_table} m ON a.default_model_id = m.id 
            WHERE a.id = %d
        ", $id));
	}

	/**
	 * Сохранение ассистента
	 */
	public function save_assistant($data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_assistants';

		// Конвертация типов
		$data['max_tokens'] = intval($data['max_tokens'] ?? 1000);
		$data['temperature'] = floatval($data['temperature'] ?? 0.7);
		$data['history_enabled'] = !empty($data['history_enabled']) ? 1 : 0;
		$data['history_messages_count'] = intval($data['history_messages_count'] ?? 10);
		$data['knowledge_base_enabled'] = !empty($data['knowledge_base_enabled']) ? 1 : 0;
		$data['default_model_id'] = !empty($data['default_model_id']) ? intval($data['default_model_id']) : null;

		if (!empty($data['id'])) {
			$wpdb->update($table_name, $data, ['id' => $data['id']]);
			return $data['id'];
		} else {
			$wpdb->insert($table_name, $data);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Удаление ассистента
	 */
	public function delete_assistant($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_assistants';

		return $wpdb->delete($table_name, ['id' => $id]);
	}
}
