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

		foreach ($history as $message) {
			$formatted[] = [
				'role' => $message->role,
				'content' => $message->content
			];
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
}
