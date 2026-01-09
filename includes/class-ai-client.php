<?php

/**
 * Клиент для работы с OpenAI-совместимыми API
 */

class RDS_AIE_AI_Client
{

	private $model_manager;
	private $assistant_manager;
	private $history_manager;  // НОВЫЙ

	public function __construct($model_manager, $assistant_manager, $history_manager)
	{
		$this->model_manager = $model_manager;
		$this->assistant_manager = $assistant_manager;
		$this->history_manager = $history_manager;  // НОВЫЙ
	}

	/**
	 * Основной метод для отправки сообщения в ИИ
	 */
	public function chat_completion($params)
	{
		$defaults = [
			'model_id' => 0,
			'assistant_id' => 0,
			'message' => '',
			'session_id' => '',
			'plugin_id' => 'default',
			'override_params' => []
		];

		$params = wp_parse_args($params, $defaults);

		// Получение настроек модели и ассистента
		$model = $this->get_model_settings($params['model_id'], $params['assistant_id']);
		$assistant = $this->assistant_manager->get($params['assistant_id']);
		$assistant_params = $this->assistant_manager->get_default_params($params['assistant_id']);

		// Объединение параметров
		$request_params = $this->merge_params($assistant_params, $params['override_params']);

		// Получаем session_id
		$session_id = $this->get_session_id($params['session_id'], $params['plugin_id']);

		// Подготовка сообщений для отправки
		$messages = $this->prepare_messages(
			$params['assistant_id'],
			$params['message'],
			$session_id,
			$params['plugin_id'],
			$assistant
		);

		// Сохраняем сообщение пользователя в историю
		$this->history_manager->save_message([
			'session_id' => $session_id,
			'plugin_id' => $params['plugin_id'],
			'assistant_id' => $params['assistant_id'],
			'model_id' => !empty($params['model_id']) ? $params['model_id'] : null,
			'role' => 'user',
			'content' => $params['message'],
			'tokens' => $this->history_manager->count_tokens($params['message'])
		]);

		// Подготовка данных для запроса
		$request_data = $this->prepare_request_data($model['model_name'], $messages, $request_params);

		// Отправка запроса
		$response = $this->make_api_request($model['base_url'], $model['api_key'], $request_data);

		// Извлечение ответа
		$response_content = '';
		if (isset($response['choices'][0]['message']['content'])) {
			$response_content = trim($response['choices'][0]['message']['content']);
		} elseif (isset($response['error']['message'])) {
			throw new Exception($response['error']['message']);
		} elseif (isset($response['message']['content'])) {
			$response_content = trim($response['message']['content']);
		} else {
			throw new Exception(__('Unknown API response format.', 'rds-ai-engine'));
		}

		// Сохраняем ответ ассистента в историю
		$this->history_manager->save_message([
			'session_id' => $session_id,
			'plugin_id' => $params['plugin_id'],
			'assistant_id' => $params['assistant_id'],
			'model_id' => !empty($params['model_id']) ? $params['model_id'] : null,
			'role' => 'assistant',
			'content' => $response_content,
			'tokens' => $this->history_manager->count_tokens($response_content)
		]);

		return $response_content;
	}

	/**
	 * Подготовка сообщений с учетом истории
	 */
	private function prepare_messages($assistant_id, $user_message, $session_id, $plugin_id, $assistant)
	{
		$messages = [];

		// Добавляем системный промпт
		$system_prompt = $this->assistant_manager->get_system_prompt($assistant_id);
		if (!empty($system_prompt)) {
			$messages[] = [
				'role' => 'system',
				'content' => $system_prompt
			];

			// Сохраняем системный промпт в историю (только если его еще нет)
			$this->save_system_prompt_if_needed($assistant_id, $session_id, $plugin_id, $system_prompt);
		}

		// Добавляем историю диалога, если включена
		if ($assistant && $assistant->history_enabled) {
			$history_messages = $this->history_manager->get_assistant_history(
				$session_id,
				$assistant_id,
				[
					'history_enabled' => $assistant->history_enabled,
					'history_messages_count' => $assistant->history_messages_count
				]
			);

			// ВАЖНО: Фильтруем системные сообщения (они уже добавлены)
			foreach ($history_messages as $history_message) {
				// Пропускаем системные сообщения (role === 'system')
				// и текущее пользовательское сообщение (если оно уже есть в истории)
				if ($history_message['role'] !== 'system') {
					// Проверяем, не является ли это текущим сообщением пользователя
					if (!($history_message['role'] === 'user' && $history_message['content'] === $user_message)) {
						$messages[] = $history_message;
					}
				}
			}
		}

		// Добавляем текущее сообщение пользователя
		$messages[] = [
			'role' => 'user',
			'content' => $user_message
		];

		// Отладочное логирование
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine - Prepared messages: ' . json_encode([
				'total_messages' => count($messages),
				'system_prompt_length' => strlen($system_prompt),
				'history_messages_count' => $assistant ? $assistant->history_messages_count : 0,
				'history_enabled' => $assistant ? $assistant->history_enabled : false,
				'messages_sample' => array_map(function ($msg) {
					return [
						'role' => $msg['role'],
						'content_length' => strlen($msg['content']),
						'content_preview' => substr($msg['content'], 0, 50) . '...'
					];
				}, $messages)
			]));
		}

		return $messages;
	}

	/**
	 * Сохранение системного промпта в историю (один раз)
	 */
	private function save_system_prompt_if_needed($assistant_id, $session_id, $plugin_id, $system_prompt)
	{
		// Проверяем, есть ли уже системный промпт для этой сессии
		$existing_system = $this->history_manager->get_history($session_id, 1);
		$has_system = false;

		foreach ($existing_system as $message) {
			if ($message->role === 'system') {
				$has_system = true;
				break;
			}
		}

		// Если системного промпта еще нет - сохраняем
		if (!$has_system) {
			$this->history_manager->save_message([
				'session_id' => $session_id,
				'plugin_id' => $plugin_id,
				'assistant_id' => $assistant_id,
				'role' => 'system',
				'content' => $system_prompt,
				'tokens' => $this->history_manager->count_tokens($system_prompt)
			]);
		}
	}

	/**
	 * Получение или создание session_id
	 */
	private function get_session_id($provided_session_id, $plugin_id)
	{
		if (!empty($provided_session_id)) {
			return $provided_session_id;
		}

		// Для плагина по умолчанию используем cookie-based сессию
		if ($plugin_id === 'default') {
			return $this->history_manager->get_current_session_id();
		}

		// Для других плагинов генерируем на основе plugin_id
		return md5($plugin_id . '_' . time() . '_' . wp_rand());
	}

	/**
	 * Подготовка данных запроса с правильными типами
	 */
	private function prepare_request_data($model_name, $messages, $params)
	{
		$request_data = [
			'model' => $model_name,
			'messages' => $messages
		];

		// Добавляем max_tokens, если указан (как число)
		if (isset($params['max_tokens'])) {
			$request_data['max_tokens'] = (int)$params['max_tokens'];
		}

		// Добавляем temperature, если указана (как число с плавающей точкой)
		if (isset($params['temperature'])) {
			$request_data['temperature'] = (float)$params['temperature'];
		}

		// Дополнительные параметры для совместимости
		$additional_params = [
			'top_p' => 'float',
			'frequency_penalty' => 'float',
			'presence_penalty' => 'float',
			'stream' => 'bool',
			'stop' => 'array'
		];

		foreach ($additional_params as $param => $type) {
			if (isset($params[$param])) {
				switch ($type) {
					case 'float':
						$request_data[$param] = (float)$params[$param];
						break;
					case 'int':
						$request_data[$param] = (int)$params[$param];
						break;
					case 'bool':
						$request_data[$param] = (bool)$params[$param];
						break;
					case 'array':
						$request_data[$param] = (array)$params[$param];
						break;
					default:
						$request_data[$param] = $params[$param];
				}
			}
		}

		return $request_data;
	}

	/**
	 * Получение настроек модели
	 */
	private function get_model_settings($model_id, $assistant_id)
	{
		$model = null;

		// Если указан конкретный model_id - используем его
		if (!empty($model_id)) {
			$model = $this->model_manager->get($model_id);
		}
		// Иначе используем модель по умолчанию из ассистента
		elseif (!empty($assistant_id)) {
			$assistant = $this->assistant_manager->get($assistant_id);
			if ($assistant && !empty($assistant->default_model_id)) {
				$model = $this->model_manager->get($assistant->default_model_id);
			}
		}

		// Если модель все еще не найдена, берем модель по умолчанию
		if (empty($model)) {
			$model = $this->model_manager->get_default_model();
		}

		if (empty($model)) {
			throw new Exception(__('No AI model configured.', 'rds-ai-engine'));
		}

		return [
			'base_url' => $model->base_url,
			'model_name' => $model->model_name,
			'api_key' => $model->api_key
		];
	}

	/**
	 * Объединение параметров
	 */
	private function merge_params($assistant_params, $override_params)
	{
		if (empty($assistant_params)) {
			return $override_params;
		}

		return array_merge((array)$assistant_params, $override_params);
	}

	/**
	 * Отправка запроса к API
	 */
	private function make_api_request($base_url, $api_key, $data)
	{
		$url = trailingslashit($base_url) . 'chat/completions';

		// Для отладки - логируем запрос
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Request to: ' . $url);
			error_log('RDS AI Engine Request data: ' . json_encode($data));
		}

		$args = [
			'timeout' => 60,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key
			],
			'body' => wp_json_encode($data)
		];

		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			throw new Exception($response->get_error_message());
		}

		$body = wp_remote_retrieve_body($response);
		$response_code = wp_remote_retrieve_response_code($response);

		// Для отладки - логируем ответ
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Response code: ' . $response_code);
			error_log('RDS AI Engine Response body: ' . $body);
		}

		$decoded = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			// Если это не JSON, возможно, это ошибка сервера
			if (strpos($body, '<html') !== false || strpos($body, '<!DOCTYPE') !== false) {
				throw new Exception(__('Server returned HTML instead of JSON. Check the API URL.', 'rds-ai-engine'));
			}
			throw new Exception(__('Invalid JSON response from API. Response: ', 'rds-ai-engine') . substr($body, 0, 200));
		}

		if ($response_code < 200 || $response_code >= 300) {
			$error_msg = isset($decoded['error']['message'])
				? $decoded['error']['message']
				: (isset($decoded['message'])
					? $decoded['message']
					: sprintf(__('API request failed with status code %d', 'rds-ai-engine'), $response_code));
			throw new Exception($error_msg);
		}

		return $decoded;
	}

	/**
	 * Проверка соединения с API
	 */
	public function test_connection($base_url, $api_key, $model_name)
	{
		try {
			$test_data = $this->prepare_request_data($model_name, [
				['role' => 'user', 'content' => 'Hello']
			], ['max_tokens' => 5]);

			$response = $this->make_api_request($base_url, $api_key, $test_data);
			return [
				'success' => true,
				'message' => __('Connection successful.', 'rds-ai-engine')
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
	}

	/**
	 * Получение истории диалога (для отладки)
	 */
	public function get_conversation_history($session_id, $limit = 10)
	{
		return $this->history_manager->get_formatted_history($session_id, $limit);
	}

	/**
	 * Получение детальной информации о подготовке запроса (для отладки)
	 */
	public function get_debug_info($session_id, $assistant_id, $user_message)
	{
		$assistant = $this->assistant_manager->get($assistant_id);

		return [
			'session_id' => $session_id,
			'assistant' => $assistant ? [
				'id' => $assistant->id,
				'name' => $assistant->name,
				'history_enabled' => (bool)$assistant->history_enabled,
				'history_messages_count' => $assistant->history_messages_count
			] : null,
			'history_raw' => $this->history_manager->get_history($session_id, 20),
			'history_formatted' => $this->history_manager->get_formatted_history($session_id, 20),
			'messages_prepared' => $this->prepare_messages(
				$assistant_id,
				$user_message,
				$session_id,
				'test_chat',
				$assistant
			),
			'timestamp' => current_time('mysql')
		];
	}

	/**
	 * Получение параметров запроса для отладки
	 */
	public function get_request_params_debug($assistant_id, $model_id)
	{
		$model = $this->get_model_settings($model_id, $assistant_id);
		$assistant_params = $this->assistant_manager->get_default_params($assistant_id);

		return [
			'model' => $model['model_name'],
			'base_url' => $model['base_url'],
			'assistant_params' => $assistant_params,
			'temperature' => isset($assistant_params['temperature']) ? (float)$assistant_params['temperature'] : 0.7,
			'max_tokens' => isset($assistant_params['max_tokens']) ? (int)$assistant_params['max_tokens'] : 1000
		];
	}

	/**
	 * Подготовка сообщений с учетом истории (публичная версия для отладки)
	 */
	public function prepare_messages_debug($assistant_id, $user_message, $session_id, $plugin_id)
	{
		$assistant = $this->assistant_manager->get($assistant_id);
		return $this->prepare_messages($assistant_id, $user_message, $session_id, $plugin_id, $assistant);
	}

	/**
	 * Подготовка полного JSON запроса для отладки
	 */
	public function get_full_request_json_debug($assistant_id, $model_id, $user_message, $session_id, $plugin_id)
	{
		$model = $this->get_model_settings($model_id, $assistant_id);
		$assistant = $this->assistant_manager->get($assistant_id);
		$assistant_params = $this->assistant_manager->get_default_params($assistant_id);

		$messages = $this->prepare_messages($assistant_id, $user_message, $session_id, $plugin_id, $assistant);

		$request_data = [
			'model' => $model['model_name'],
			'messages' => $messages,
			'max_tokens' => isset($assistant_params['max_tokens']) ? (int)$assistant_params['max_tokens'] : 1000,
			'temperature' => isset($assistant_params['temperature']) ? (float)$assistant_params['temperature'] : 0.7
		];

		return [
			'url' => trailingslashit($model['base_url']) . 'chat/completions',
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . substr($model['api_key'], 0, 10) . '...' // Безопасный показ ключа
			],
			'body' => $request_data,
			'body_json' => json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		];
	}
}
