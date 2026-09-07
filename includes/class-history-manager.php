<?php

/**
 * Класс для управления историей диалогов
 */

class RDS_AIE_History_Manager
{

	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Сохранение сообщения в историю
	 */
	public function save_message($params = [])
	{
		$defaults = [
			'plugin_id' => 'default',
			'user_id' => get_current_user_id(),
			'session_id' => '',
			'assistant_id' => null,
			'model_id' => null,
			'role' => 'user',
			'content' => '',
			'metadata' => null,
			'tokens' => 0
		];

		$params = wp_parse_args($params, $defaults);

		// Если нет session_id, генерируем новый
		if (empty($params['session_id'])) {
			$params['session_id'] = $this->generate_session_id();
		}

		// Сохраняем сообщение
		return $this->db->save_conversation_message($params);
	}

	/**
	 * Получение истории диалога
	 */
	public function get_history($session_id, $limit = 10)
	{
		return $this->db->get_conversation_history($session_id, $limit);
	}

	/**
	 * Получение истории в формате для OpenAI API
	 */
	public function get_formatted_history($session_id, $limit = 10)
	{
		$history = $this->get_history($session_id, $limit);
		$formatted = [];

		// ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[RDS AIE Debug formatted history] Formatted history Start');
		}

		foreach ($history as $message) {
			$msg = [
				'role' => $message->role,
				'content' => $message->content
			];

			// Восстанавливаем метаданные (tool_calls, tool_call_id, reasoning_content)
			if (!empty($message->metadata)) {
				$meta = json_decode($message->metadata, true);
				
				if (is_array($meta)) {
					// Для assistant: добавляем tool_calls и reasoning_content
					if ($message->role === 'assistant') {
						if (isset($meta['tool_calls']) && is_array($meta['tool_calls'])) {
							$msg['tool_calls'] = $meta['tool_calls'];
							// Если есть tool_calls, content может быть null или пустым
							if (empty($msg['content'])) {
								$msg['content'] = null; 
							}
						}
						if (isset($meta['reasoning_content'])) {
							$msg['reasoning_content'] = $meta['reasoning_content'];
						}
					}
					
					// Для tool: добавляем tool_call_id и name
					if ($message->role === 'tool') {
						if (isset($meta['tool_call_id'])) {
							$msg['tool_call_id'] = $meta['tool_call_id'];
						}
						if (isset($meta['name'])) {
							$msg['name'] = $meta['name'];
						}
					}
				}
			}

			$formatted[] = $msg;
		}

		// ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[RDS AIE Debug formatted history] ' . json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}

		return $formatted;
	}
	
	/**
	 * Получение истории с учетом настроек ассистента
	 */
	public function get_assistant_history($session_id, $assistant_id, $assistant_settings = [])
	{
		$limit = isset($assistant_settings['history_messages_count'])
			? (int)$assistant_settings['history_messages_count']
			: 10;

		// Получаем ВСЮ историю
		$all_history = $this->get_formatted_history($session_id, 100);

		// Если история отключена - возвращаем пустой массив
		if (isset($assistant_settings['history_enabled']) && !$assistant_settings['history_enabled']) {
			return [];
		}

		// Ограничиваем количество сообщений (берем последние N)
		if ($limit > 0 && count($all_history) > $limit) {
			$all_history = array_slice($all_history, -$limit);
		}

		return $all_history;
	}

	/**
	 * Очистка истории диалога
	 */
	public function clear_history($session_id)
	{
		return $this->db->delete_conversation_session($session_id);
	}

	/**
	 * Очистка старых записей
	 */
	public function cleanup_old_history($days = 7)
	{
		return $this->db->cleanup_old_conversations($days);
	}

	/**
	 * Очистка старых записей генерации изображений
	 */
	public function cleanup_old_generations($hours = 1)
	{
		return $this->db->cleanup_old_generations($hours);
	}

	/**
	 * Генерация session_id
	 */
	private function generate_session_id()
	{
		// Используем комбинацию из user_id, времени и случайной строки
		$user_id = get_current_user_id();
		$time = time();
		$random = wp_generate_password(8, false);

		return md5("{$user_id}_{$time}_{$random}");
	}

	/**
	 * Получение session_id для текущей сессии
	 */
	public function get_current_session_id()
	{
		// Пытаемся получить session_id из cookie
		if (!empty($_COOKIE['rds_aie_session'])) {
			return sanitize_text_field($_COOKIE['rds_aie_session']);
		}

		// Или генерируем новый
		$session_id = $this->generate_session_id();

		// Сохраняем в cookie на 24 часа
		setcookie('rds_aie_session', $session_id, time() + 86400, '/');

		return $session_id;
	}

	/**
	 * Подсчет токенов в сообщении (упрощенный метод)
	 */
	public function count_tokens($text)
	{
		// Упрощенный подсчет: примерно 4 символа = 1 токен
		// В реальном проекте лучше использовать библиотеку для подсчета токенов
		return ceil(strlen($text) / 4);
	}

	/**
	 * Проверка и выполнение суммаризации истории
	 * @param string $session_id ID сессии
	 * @param int $agent_id ID агента
	 * @return bool Была ли выполнена суммаризация
	 */
	public function check_and_summarize($session_id, $agent_id) {
		global $wpdb;
		$agents_table = $wpdb->prefix . 'rds_aie_agents';
		$conv_table = $wpdb->prefix . 'rds_aie_conversations';

		// 1. Получаем настройки агента
		$agent = $wpdb->get_row($wpdb->prepare(
			"SELECT summary_enabled, max_history_size, min_last_messages, summary_model_id FROM {$agents_table} WHERE id = %d",
			$agent_id
		));

		if (!$agent || !$agent->summary_enabled) {
			return false;
		}

		// 2. Получаем всю историю сессии
		$messages = $wpdb->get_results($wpdb->prepare(
			"SELECT id, role, content FROM {$conv_table} WHERE session_id = %s ORDER BY created_at ASC",
			$session_id
		));

		if (count($messages) <= $agent->min_last_messages) {
			return false; // Слишком мало сообщений для суммаризации
		}

		// 3. Проверяем общий размер символов
		$total_size = 0;
		foreach ($messages as $msg) {
			$total_size += strlen($msg->content);
		}

		if ($total_size < $agent->max_history_size) {
			return false; // Размер еще не превышен
		}

		// 4. Определяем, какие сообщения нужно суммаризировать
		// Оставляем последние N сообщений нетронутыми
		$messages_to_summarize = array_slice($messages, 0, count($messages) - $agent->min_last_messages);
		
		if (empty($messages_to_summarize)) {
			return false;
		}

		// 5. Формируем текст для суммаризации
		$summary_text = "";
		foreach ($messages_to_summarize as $msg) {
			$role_label = ($msg->role === 'user') ? 'User' : 'Assistant';
			$summary_text .= "{$role_label}: {$msg->content}\n\n";
		}

		// 6. Вызываем ИИ для получения резюме
		$summary_prompt = "Summarize the following conversation history concisely. Keep key facts and context:\n\n{$summary_text}";
		
		$model_manager = new RDS_AIE_Model_Manager($this->db); // Или через Main, если доступно
		$model = $agent->summary_model_id 
			? $model_manager->get($agent->summary_model_id) 
			: $model_manager->get_default_model();

		if (!$model) return false;

		$url = trailingslashit($model->base_url) . 'chat/completions';
		$body = [
			'model' => $model->model_name,
			'messages' => [
				['role' => 'system', 'content' => 'You are a helpful assistant that summarizes conversations.'],
				['role' => 'user', 'content' => $summary_prompt]
			],
			'temperature' => 0.3
		];

		$args = [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $model->api_key
			],
			'body' => wp_json_encode($body)
		];

		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) return false;

		$data = json_decode(wp_remote_retrieve_body($response), true);
		$summary_content = $data['choices'][0]['message']['content'] ?? '';

		if (empty($summary_content)) return false;

		// 7. Заменяем старые сообщения на одно сообщение с резюме
		$ids_to_delete = array_map(function($m) { return $m->id; }, $messages_to_summarize);
		
		// Удаляем старые записи
		$ids_placeholder = implode(',', array_fill(0, count($ids_to_delete), '%d'));
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$conv_table} WHERE id IN ($ids_placeholder)",
			$ids_to_delete
		));

		// Вставляем резюме (как системное или специальное сообщение)
		$this->save_message([
			'session_id' => $session_id,
			'assistant_id' => $agent_id,
			'role' => 'system', // Используем system, чтобы оно всегда было в начале контекста
			'content' => "[Conversation Summary]:\n{$summary_content}",
			'tokens' => $this->count_tokens($summary_content)
		]);

		return true;
	}
}
