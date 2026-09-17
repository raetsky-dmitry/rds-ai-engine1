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
		$table_name = $wpdb->prefix . 'rds_aie_models';
		$sql1 = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id int(11) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			base_url varchar(255) NOT NULL,
			model_name varchar(255) NOT NULL,
			api_key text NOT NULL,
			max_tokens int(11) DEFAULT 4096,
			is_default tinyint(1) DEFAULT 0,
			model_type model_type ENUM('text', 'image', 'embedding', 'both') NOT NULL DEFAULT 'text',
			capabilities text,
			image_params text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY is_default (is_default),
			KEY model_type (model_type)
    	) {$charset_collate};";

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
			KEY default_model_id (default_model_id)
			/*
			* ВНИМАНИЕ: внешний ключ здесь сознательно убран.
			* Причина: default_model_id имеет тип BIGINT UNSIGNED, а referenced models.id — INT (знаковый).
			* InnoDB требует СОВПАДЕНИЯ типов для FOREIGN KEY, из-за чего на «чистых» установках
			* CREATE TABLE для таблицы ассистентов молча падал (ошибка 1215), и таблица не создавалась.
			* Referential integrity обеспечивается на уровне кода (RDS_AIE_Model_Manager::delete()
			* запрещает удаление модели, используемой ассистентами).
			*/
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
			metadata TEXT DEFAULT NULL,
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

		// Таблица сгенерированных изображений
		$table_name = $wpdb->prefix . 'rds_aie_generations';
		$sql_generations = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id int(11) NOT NULL AUTO_INCREMENT,
			model_id int(11) NOT NULL,
			session_id varchar(100),
			plugin_id varchar(50) DEFAULT 'default',
			user_id int(11) DEFAULT 0,
			type enum('text', 'image') DEFAULT 'text',
			prompt text,
			parameters text,
			response_data longtext,
			response_format varchar(20) DEFAULT 'text',
			tokens_used int(11) DEFAULT 0,
			status enum('pending', 'success', 'error') DEFAULT 'pending',
			error_message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY model_id (model_id),
			KEY session_id (session_id),
			KEY type (type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Таблица агентов
		$sql_agents = "CREATE TABLE IF NOT EXISTS {$table_prefix}agents (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(100) DEFAULT '';
			system_prompt TEXT NOT NULL,
			default_model_id BIGINT(20) UNSIGNED,
			max_iterations INT DEFAULT 5,
			temperature DECIMAL(3,2) DEFAULT 0.7,
			summary_enabled TINYINT(1) DEFAULT 0,
			max_history_size INT DEFAULT 4000,
			min_last_messages INT DEFAULT 5,
			summary_model_id BIGINT(20) UNSIGNED,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Таблица инструментов агентов
		$sql_agent_tools = "CREATE TABLE IF NOT EXISTS {$table_prefix}agent_tools (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_id BIGINT(20) UNSIGNED NOT NULL,
			tool_name VARCHAR(100) NOT NULL,
			tool_schema TEXT NOT NULL,
			is_active TINYINT(1) DEFAULT 1,
			PRIMARY KEY (id),
			KEY agent_id (agent_id)
		) $charset_collate;";

		// Таблица навыков
		$sql_skills = "CREATE TABLE IF NOT EXISTS {$table_prefix}skills (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL UNIQUE,
			description TEXT,
			url TEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_skill_name (name)
		) $charset_collate;";

		// Таблица связи агентов и навыков
		$sql_agent_skills = "CREATE TABLE IF NOT EXISTS {$table_prefix}agent_skills (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_id BIGINT(20) UNSIGNED NOT NULL,
			skill_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_agent_skill (agent_id, skill_id),
			KEY agent_id (agent_id),
			KEY skill_id (skill_id)
		) $charset_collate;";

		// Таблица базы знаний (RAG)
		$sql_knowledge = "CREATE TABLE IF NOT EXISTS {$table_prefix}knowledge_base (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			document_id VARCHAR(100) NOT NULL COMMENT 'Unique ID for the source document',
			title VARCHAR(255) DEFAULT '',
			content LONGTEXT NOT NULL,
			embedding LONGTEXT NOT NULL COMMENT 'JSON array of floats',
			source_name VARCHAR(255) DEFAULT '' COMMENT 'Original filename or manual label',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY document_id (document_id)
		) $charset_collate;";
	 
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// Список таблиц: имя => SQL (порядок важен: сначала модели, потом ссылающиеся).
		$tables = [
			$wpdb->prefix . 'rds_aie_models'        => $sql1,
			$wpdb->prefix . 'rds_aie_assistants'    => $sql2,
			$wpdb->prefix . 'rds_aie_conversations' => $sql3,
			$wpdb->prefix . 'rds_aie_generations'   => $sql_generations,
			$wpdb->prefix . 'rds_aie_agents'        => $sql_agents,
			$wpdb->prefix . 'rds_aie_agent_tools'   => $sql_agent_tools,
			$wpdb->prefix . 'rds_aie_skills'        => $sql_skills,
			$wpdb->prefix . 'rds_aie_agent_skills'  => $sql_agent_skills,
			$wpdb->prefix . 'rds_aie_knowledge_base'  => $sql_knowledge,
		];

		$created = [];
		$errors  = [];

		foreach ($tables as $table => $sql) {
			// Штатный механизм WP (создаёт и обновляет структуру).
			dbDelta($sql);

			// dbDelta может «молча» не создать таблицу (например, из-за FOREIGN KEY,
			// особенностей ENUM или конкретной версии MySQL). Проверяем фактическое
			// наличие таблицы и, если её нет, создаём напрямую и логируем проблему.
			$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

			if ($exists !== $table) {
				// Запасной вариант: прямое создание (dbDelta мог «молча» не создать).
				$wpdb->query($sql);

				if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
					$created[] = $table;
				} else {
					$errors[] = $table . ' (last_error: ' . $wpdb->last_error . ')';
				}

				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('RDS AI Engine: таблица не была создана dbDelta, повторная попытка напрямую: ' . $table . ' | last_error: ' . $wpdb->last_error);
				}
			}
		}

		if (!empty($created) && defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine: восстановлены таблицы: ' . implode(', ', $created));
		}

		if (!empty($errors)) {
			error_log('RDS AI Engine: НЕ удалось создать таблицы: ' . implode('; ', $errors));
		}

		return $tables;
	}

	/**
	 * Обновление структуры БД.
	 *
	 * Вызывается при обновлении версии плагина (см. rds_aie_check_update в rds-ai-engine.php).
	 * dbDelta обрабатывает и создание, и изменение структуры, поэтому здесь достаточно
	 * повторного запуска create_tables().
	 */
	public function update_tables()
	{
		return $this->create_tables();
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
			'models',
			'generations',
			'agents',
			'agent_tools',
			'skills',
			'agent_skills',
			'knowledge_base'
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
			'metadata' => null,
			'tokens' => 0
		];
		
		$data = wp_parse_args($data, $defaults);

		// Сериализуем metadata в JSON, если это массив
		if (!empty($data['metadata']) && is_array($data['metadata'])) {
			$data['metadata'] = wp_json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
		} else {
            // Если metadata пустое или null, явно ставим NULL для БД
            $data['metadata'] = !empty($data['metadata']) ? $data['metadata'] : null;
        }

		$result = $wpdb->insert($table_name, $data);

		if (false === $result) {
			error_log('RDS AI Engine: не удалось сохранить сообщение в историю. last_error: ' . $wpdb->last_error);
		}

		return $result;
	}

	/**
	 * Получение истории диалога
	 */
	public function get_conversation_history($session_id, $limit = 10)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		return $wpdb->get_results($wpdb->prepare(
			"SELECT role, content, metadata, created_at 
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

		$query = "SELECT role, content, metadata, created_at 
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

		$date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days", current_time('timestamp')));

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

	/**
	 * Сохранение результата генерации
	 */
	public function save_generation($data)
	{
		global $wpdb;

		$defaults = [
			'user_id' => get_current_user_id(),
			'created_at' => current_time('mysql')
		];

		$data = wp_parse_args($data, $defaults);

		// Сериализуем массивы в JSON
		if (isset($data['parameters']) && is_array($data['parameters'])) {
			$data['parameters'] = wp_json_encode($data['parameters'], JSON_UNESCAPED_UNICODE);
		}

		if (isset($data['response_data']) && is_array($data['response_data'])) {
			$data['response_data'] = wp_json_encode($data['response_data'], JSON_UNESCAPED_UNICODE);
		}

		$table_name = $wpdb->prefix . 'rds_aie_generations';

		if (isset($data['id']) && $data['id'] > 0) {
			// Обновление
			$wpdb->update($table_name, $data, ['id' => $data['id']]);
			return $data['id'];
		} else {
			// Вставка
			$wpdb->insert($table_name, $data);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Получение генерации по ID
	 */
	public function get_generation($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_generations';

		$generation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d",
			$id
		));

		if ($generation) {
			// Десериализуем JSON поля
			if (!empty($generation->parameters)) {
				$generation->parameters = json_decode($generation->parameters, true);
			}

			if (!empty($generation->response_data)) {
				$generation->response_data = json_decode($generation->response_data, true);
			}
		}

		return $generation;
	}

	/**
	 * Получение генераций по сессии
	 */
	public function get_generations_by_session($session_id, $limit = 20)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_generations';

		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE session_id = %s ORDER BY created_at DESC LIMIT %d",
			$session_id,
			$limit
		));

		foreach ($results as &$result) {
			if (!empty($result->parameters)) {
				$result->parameters = json_decode($result->parameters, true);
			}

			if (!empty($result->response_data)) {
				$result->response_data = json_decode($result->response_data, true);
			}
		}

		return $results;
	}

	/**
	 * Очистка старых генераций (по количеству часов)
	 */
	public function cleanup_old_generations($hours = 1)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_generations';

		$date = gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours", current_time('timestamp')));

		// Для отладки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Image Delete Datet: ' . $date);
		}

		return $wpdb->query($wpdb->prepare(
			"DELETE FROM {$table_name} WHERE created_at < %s",
			$date
		));
	}

	/**
	 * Сохранение агента
	 */
	public function save_agent($data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		
		$data['max_iterations'] = intval($data['max_iterations'] ?? 5);
		$data['temperature'] = floatval($data['temperature'] ?? 0.7);
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
	 * Получение всех агентов
	 */
	public function get_agents() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY name ASC");
	}

	/**
	 * Получение агента по ID
	 */
	public function get_agent($id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
	}

	/**
	 * Получение агента по slug
	 */
	public function get_agent_by_slug($slug) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE slug = %s LIMIT 1",
			sanitize_key($slug)
		)); 
	}

	/**
	 * Удаление агента
	 */
	public function delete_agent($id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		$tools_table = $wpdb->prefix . 'rds_aie_agent_tools';
		
		$wpdb->delete($tools_table, ['agent_id' => $id]);
		return $wpdb->delete($table_name, ['id' => $id]);
	}

	/**
	 * Обновление настроек агента для суммаризации истории
	 */
	public function update_agent_summary_settings($agent_id, $settings) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agents';
		
		$data = [
			'summary_enabled' => !empty($settings['summary_enabled']) ? 1 : 0,
			'max_history_size' => intval($settings['max_history_size'] ?? 4000),
			'min_last_messages' => intval($settings['min_last_messages'] ?? 3),
			'summary_model_id' => !empty($settings['summary_model_id']) ? intval($settings['summary_model_id']) : null
		];
		
		return $wpdb->update($table_name, $data, ['id' => $agent_id]);
	}

	/**
	 * Сохранение инструмента для агента
	 */
	public function save_agent_tool($data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_tools';
		
		if (!empty($data['id'])) {
			$wpdb->update($table_name, $data, ['id' => $data['id']]);
			return $data['id'];
		} else {
			$wpdb->insert($table_name, $data);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Получение инструментов агента
	 */
	public function get_agent_tools($agent_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_tools';
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE agent_id = %d AND is_active = 1", 
			$agent_id
		));
	}
	
	/**
	 * Сохранение навыка (создание или обновление по имени)
	 */
	public function save_skill($data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_skills';
		
		$data['name'] = sanitize_key($data['name']);
		$data['description'] = sanitize_textarea_field($data['description'] ?? '');
		$data['url'] = esc_url_raw($data['url']);

		// Проверяем существование по имени
		$existing_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE name = %s",
			$data['name']
		));

		if ($existing_id) {
			$wpdb->update($table_name, $data, ['id' => $existing_id]);
			return $existing_id;
		} else {
			$wpdb->insert($table_name, $data);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Получение всех навыков
	 */
	public function get_skills() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_skills';
		return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY name ASC");
	}

	/**
	 * Получение навыка по ID
	 */
	public function get_skill($id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_skills';
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
	}

	/**
	 * Удаление навыка
	 */
	public function delete_skill($id) {
		global $wpdb;
		$skills_table = $wpdb->prefix . 'rds_aie_skills';
		$rel_table = $wpdb->prefix . 'rds_aie_agent_skills';
		
		$wpdb->delete($rel_table, ['skill_id' => $id]);
		return $wpdb->delete($skills_table, ['id' => $id]);
	}

	/**
	 * Привязка навыка к агенту
	 */
	public function assign_skill_to_agent($agent_id, $skill_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_skills';
		
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE agent_id = %d AND skill_id = %d",
			$agent_id, $skill_id
		));

		if (!$exists) {
			return $wpdb->insert($table_name, [
				'agent_id' => intval($agent_id),
				'skill_id' => intval($skill_id)
			]);
		}
		return true;
	}

	/**
	 * Отвязка навыка от агента
	 */
	public function unassign_skill_from_agent($agent_id, $skill_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_skills';
		return $wpdb->delete($table_name, [
			'agent_id' => intval($agent_id),
			'skill_id' => intval($skill_id)
		]);
	}

	/**
	 * Получение всех навыков конкретного агента
	 */
	public function get_agent_skills($agent_id) {
		global $wpdb;
		$skills_table = $wpdb->prefix . 'rds_aie_skills';
		$rel_table = $wpdb->prefix . 'rds_aie_agent_skills';
		
		return $wpdb->get_results($wpdb->prepare(
			"SELECT s.* FROM {$skills_table} s 
			 INNER JOIN {$rel_table} r ON s.id = r.skill_id 
			 WHERE r.agent_id = %d 
			 ORDER BY s.name ASC",
			$agent_id
		));
	}

		/**
	 * Сохранение чанка в базу знаний
	 */
	public function save_knowledge_chunk($data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_knowledge_base';
		
		return $wpdb->insert($table_name, [
			'document_id' => sanitize_text_field($data['document_id']),
			'title' => sanitize_text_field($data['title'] ?? ''),
			'content' => wp_kses_post($data['content']),
			'embedding' => is_array($data['embedding']) ? wp_json_encode($data['embedding']) : $data['embedding'],
			'source_name' => sanitize_text_field($data['source_name'] ?? 'manual')
		]);
	}

	/**
	 * Получение всех чанков
	 */
	public function get_all_knowledge_chunks() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_knowledge_base';
		return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
	}	

	/**
	 * Получение списка уникальных документов (для админки)
	 */
	public function get_knowledge_documents() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_knowledge_base';
		return $wpdb->get_results("
			SELECT document_id, source_name, title, COUNT(*) as chunk_count, MAX(created_at) as last_updated 
			FROM {$table_name} 
			GROUP BY document_id 
			ORDER BY last_updated DESC
		");
	}

	/**
	 * Удаление всех чанков конкретного документа
	 */
	public function delete_knowledge_document($document_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_knowledge_base';
		return $wpdb->delete($table_name, ['document_id' => sanitize_text_field($document_id)]);
	}

	/**
	 * Очистка всей базы знаний
	 */
	public function clear_knowledge_base() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_knowledge_base';
		return $wpdb->query("TRUNCATE TABLE {$table_name}");
	}
	
}

	