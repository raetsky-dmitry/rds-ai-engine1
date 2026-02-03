<?php

/**
 * Текстовый генератор
 */

class RDS_AIE_Text_Generator extends RDS_AIE_Generator_Base
{
	/**
	 * Валидация параметров
	 */
	public function validate_params($params)
	{
		$required = ['message'];

		foreach ($required as $field) {
			if (empty($params[$field])) {
				throw new Exception(sprintf(__('Missing required parameter: %s', 'rds-ai-engine'), $field));
			}
		}

		// Проверка длины сообщения
		if (strlen($params['message']) > 4000) {
			throw new Exception(__('Message is too long', 'rds-ai-engine'));
		}

		return true;
	}

	/**
	 * Подготовка запроса к API
	 */
	public function prepare_request($input)
	{
		// Используем существующую логику из RDS_AIE_AI_Client
		// Для первого этапа просто возвращаем существующий формат
		return [
			'model' => $this->model->model_name,
			'messages' => $input['messages'] ?? [],
			'max_tokens' => $this->params['max_tokens'] ?? 1000,
			'temperature' => $this->params['temperature'] ?? 0.7
		];
	}

	/**
	 * Обработка ответа от API
	 */
	public function process_response($response)
	{
		if (isset($response['choices'][0]['message']['content'])) {
			return trim($response['choices'][0]['message']['content']);
		}

		if (isset($response['error']['message'])) {
			throw new Exception($response['error']['message']);
		}

		throw new Exception(__('Unknown API response format.', 'rds-ai-engine'));
	}

	/**
	 * Получение типа генератора
	 */
	protected function get_type()
	{
		return 'text';
	}
}
