<?php

/**
 * Графический генератор
 */

class RDS_AIE_Image_Generator extends RDS_AIE_Generator_Base
{
	private $default_params = [
		'size' => '1024x1024',
		'quality' => 'standard',
		'style' => 'vivid',
		'n' => 1,
		'response_format' => 'b64_json',
		'seed' => null  // Добавляем параметр seed
	];

	public function __construct($model, $params, $db)
	{
		// Генерируем seed, если он не задан в параметрах
		if (!isset($params['seed']) || $params['seed'] === null) {
			$params['seed'] = mt_rand(0, 99999); // Генерируем случайное число в диапазоне 0–99999
		}

		parent::__construct($model, $params, $db);
	}

	/**
	 * Валидация параметров
	 */
	public function validate_params($params)
	{
		// Проверка обязательных полей
		if (empty($params['prompt'])) {
			throw new Exception(__('Prompt is required for image generation', 'rds-ai-engine'));
		}

		// Проверка длины промпта
		if (strlen($params['prompt']) > 4000) {
			throw new Exception(__('Prompt is too long', 'rds-ai-engine'));
		}

		// Проверка параметра seed (если задан)
		if (isset($params['seed']) && (!is_numeric($params['seed']) || $params['seed'] < 0 || $params['seed'] > 99999)) {
			throw new Exception(__('Seed must be an integer between 0 and 99999', 'rds-ai-engine'));
		}

		// Проверяем, это OpenRouter или стандартный API
		$is_openrouter = false;
		if (isset($this->model->base_url) && strpos($this->model->base_url, 'openrouter.ai') !== false) {
			$is_openrouter = true;
		}

		if (!$is_openrouter) {
			// Только для не-OpenRouter проверяем стандартные параметры
			// Проверяем формат размера изображения: должен быть в формате NNNxNNN (например, 1024x1024)
			if (isset($params['size']) && !preg_match('/^\d+x\d+$/', $params['size'])) {
				throw new Exception(__('Invalid image size format. Expected format: NNNxNNN (e.g., 1024x1024)', 'rds-ai-engine'));
			}

			// Проверка количества изображений (только для не-OpenRouter)
			if (isset($params['n']) && ($params['n'] < 1 || $params['n'] > 10)) {
				throw new Exception(__('Number of images must be between 1 and 10', 'rds-ai-engine'));
			}

			// Проверка формата ответа
			$allowed_formats = ['url', 'b64_json'];
			if (isset($params['response_format']) && !in_array($params['response_format'], $allowed_formats)) {
				throw new Exception(__('Invalid response format', 'rds-ai-engine'));
			}
		} else {
			// Для OpenRouter проверяем только aspect_ratio
			$allowed_aspect_ratios = ['1:1', '4:3', '3:4', '16:9', '9:16'];
			if (isset($params['aspect_ratio']) && !in_array($params['aspect_ratio'], $allowed_aspect_ratios)) {
				throw new Exception(sprintf(__('Invalid aspect ratio. Allowed: %s', 'rds-ai-engine'), implode(', ', $allowed_aspect_ratios)));
			}
		}

		return true;
	}

	/**
	 * Подготовка запроса к API
	 */
	public function prepare_request($input)
	{
		$params = wp_parse_args($this->params, $this->default_params);

		// Для отладки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Image Generation Params: ' . json_encode($params));
		}

		// Проверяем, это OpenRouter или стандартный API
		$is_openrouter = false;
		if (isset($this->model->base_url) && strpos($this->model->base_url, 'openrouter.ai') !== false) {
			$is_openrouter = true;
		}

		if ($is_openrouter) {
			// Формат запроса для OpenRouter
			$request = [
				'model' => $this->model->model_name,
				'messages' => [
					[
						'role' => 'user',
						'content' => $params['prompt']
					]
				],
				'modalities' => ['image'],
				'seed' => (int)$params['seed']
			];

			// Добавляем response_format если указан
			if (!empty($params['response_format'])) {
				$request['response_format'] = $params['response_format'];
			}

			// Добавляем aspect_ratio если указан (только для OpenRouter)
			if (!empty($params['aspect_ratio'])) {
				$request['image_config'] = [
					'aspect_ratio' => $params['aspect_ratio']
				];
			}
		} else {
			// Стандартный формат для OpenAI
			$request = [
				'model' => $this->model->model_name,
				'prompt' => $params['prompt'],
				'n' => (int)$params['n'],
				'size' => $params['size'],
				'response_format' => $params['response_format']
			];

			// Добавляем seed в extra_body для не OpenRouter
			$request['extra_body'] = [
				'seed' => (int)$params['seed']
			];

			// Опциональные параметры
			if (!empty($params['quality'])) {
				$request['quality'] = $params['quality'];
			}

			if (!empty($params['style'])) {
				$request['style'] = $params['style'];
			}

			// Добавляем seed если он установлен
			if (isset($params['seed'])) {
				$request['seed'] = (int)$params['seed'];
			}
		}

		// Для отладки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Final Request: ' . json_encode($request));
		}

		return $request;
	}

	/**
	 * Обработка ответа от API
	 */
	public function process_response($response)
	{
		// Для отладки
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Image Response Structure: ' . json_encode(array_keys($response)));
		}

		// Проверяем формат ответа OpenRouter
		$is_openrouter = false;
		if (isset($this->model->base_url) && strpos($this->model->base_url, 'openrouter.ai') !== false) {
			$is_openrouter = true;
		}

		if ($is_openrouter) {
			// Обработка ответа OpenRouter согласно полученному тестовому ответу
			if (isset($response['choices'][0]['message'])) {
				$message = $response['choices'][0]['message'];

				// Вариант 1: Поле images содержит массив изображений
				if (isset($message['images']) && is_array($message['images'])) {
					$images = [];

					foreach ($message['images'] as $image_data) {
						if ($image_data['type'] === 'image_url' && isset($image_data['image_url']['url'])) {
							$image_url = $image_data['image_url']['url'];

							// Если это уже data URI (base64), возвращаем как есть
							if (strpos($image_url, 'data:') === 0) {
								$images[] = $image_url;
							} else {
								// Если это URL, конвертируем в base64
								$images[] = $this->url_to_base64($image_url);
							}
						}
					}

					if (!empty($images)) {
						return $images;
					}
				}

				// Вариант 2: Контент содержит изображения в массиве (старый формат)
				if (isset($message['content']) && is_array($message['content'])) {
					$images = [];

					foreach ($message['content'] as $content_part) {
						if ($content_part['type'] === 'image_url' && isset($content_part['image_url']['url'])) {
							$image_url = $content_part['image_url']['url'];

							if (strpos($image_url, 'data:') === 0) {
								$images[] = $image_url;
							} else {
								$images[] = $this->url_to_base64($image_url);
							}
						}
					}

					if (!empty($images)) {
						return $images;
					}
				}

				// Вариант 3: Контент как строка с URL изображения
				if (isset($message['content']) && is_string($message['content'])) {
					// Пытаемся найти URL изображения в тексте
					if (preg_match('/https?:\/\/[^\s]+(?:\.(?:jpg|jpeg|png|gif|webp))[^\s]*/i', $message['content'], $matches)) {
						return [$this->url_to_base64($matches[0])];
					}

					// Или ищем data URI
					if (preg_match('/data:image\/[^;]+;base64,[^\s"]+/i', $message['content'], $matches)) {
						return [$matches[0]];
					}
				}
			}
		} else {
			// Стандартная обработка для OpenAI
			if (isset($response['data'])) {
				$images = [];

				foreach ($response['data'] as $image_data) {
					if (isset($image_data['url'])) {
						// URL -> конвертируем в base64
						$images[] = $this->url_to_base64($image_data['url']);
					} elseif (isset($image_data['b64_json'])) {
						// Уже base64
						$image_type = $this->get_image_type($response, $image_data);
						$images[] = 'data:' . $image_type . ';base64,' . $image_data['b64_json'];
					}
				}

				if (!empty($images)) {
					return $images;
				}
			}
		}

		// Если ничего не найдено, проверяем ошибки
		if (isset($response['error']['message'])) {
			throw new Exception($response['error']['message']);
		}

		// Дополнительная отладочная информация
		$debug_info = [
			'response_keys' => array_keys($response),
			'has_choices' => isset($response['choices']),
			'choices_count' => isset($response['choices']) ? count($response['choices']) : 0,
			'first_choice_keys' => isset($response['choices'][0]) ? array_keys($response['choices'][0]) : null,
			'first_message_keys' => isset($response['choices'][0]['message']) ? array_keys($response['choices'][0]['message']) : null,
			'has_images' => isset($response['choices'][0]['message']['images']),
			'images_count' => isset($response['choices'][0]['message']['images']) ? count($response['choices'][0]['message']['images']) : 0,
		];

		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('RDS AI Engine Debug Info: ' . json_encode($debug_info));

			// Логируем структуру сообщения если есть
			if (isset($response['choices'][0]['message'])) {
				$message_structure = [];
				foreach ($response['choices'][0]['message'] as $key => $value) {
					if (is_array($value)) {
						$message_structure[$key] = ['type' => 'array', 'count' => count($value)];
						if ($key === 'images' && !empty($value)) {
							$message_structure[$key]['first_item'] = array_keys($value[0]);
						}
					} else {
						$message_structure[$key] = ['type' => gettype($value), 'preview' => substr((string)$value, 0, 100)];
					}
				}
				error_log('RDS AI Engine Message Structure: ' . json_encode($message_structure));
			}
		}

		throw new Exception(
			__('Image not found in API response. ', 'rds-ai-engine') .
				'Response structure: ' . json_encode($debug_info)
		);
	}

	/**
	 * Конвертация URL в base64
	 */
	private function url_to_base64($url)
	{
		// Если URL уже содержит data URI, возвращаем как есть
		if (strpos($url, 'data:') === 0) {
			return $url;
		}

		// Проверяем, является ли URL валидным
		$url = filter_var($url, FILTER_VALIDATE_URL);
		if (!$url) {
			throw new Exception(__('Invalid image URL: ', 'rds-ai-engine') . $url);
		}

		$response = wp_remote_get($url, [
			'timeout' => 30,
			'redirection' => 5,
			'user-agent' => 'RDS AI Engine/' . RDS_AIE_VERSION
		]);

		if (is_wp_error($response)) {
			throw new Exception(sprintf(
				__('Failed to download image from URL: %s', 'rds-ai-engine'),
				$response->get_error_message()
			));
		}

		$image_data = wp_remote_retrieve_body($response);
		$response_code = wp_remote_retrieve_response_code($response);

		if ($response_code !== 200) {
			throw new Exception(sprintf(
				__('Failed to download image. HTTP status: %d', 'rds-ai-engine'),
				$response_code
			));
		}

		if (empty($image_data)) {
			throw new Exception(__('Empty image data received', 'rds-ai-engine'));
		}

		// $image_type = wp_remote_retrieve_header($response, 'content-type');

		// // Если тип не определен, используем wp_check_filetype
		// if (empty($image_type) || strpos($image_type, 'image/') !== 0) {
		// 	// Создаем временный файл для проверки
		// 	$temp_file = wp_tempnam();
		// 	file_put_contents($temp_file, $image_data);

		// 	$filetype = wp_check_filetype($temp_file);
		// 	if ($filetype['type'] && strpos($filetype['type'], 'image/') === 0) {
		// 		$image_type = $filetype['type'];
		// 	}

		// 	// Удаляем временный файл
		// 	@unlink($temp_file);
		// }

		// // Если все еще не определили тип, используем дефолтный
		// if (empty($image_type)) {
		// 	$image_type = 'image/jpeg';
		// }

		$image_type = $this->get_image_type($response, $image_data);

		$base64 = base64_encode($image_data);

		// Возвращаем с префиксом data URI
		return 'data:' . $image_type . ';base64,' . $base64;
	}

	/**
	 * Определение типа изображения
	 */

	protected function get_image_type($response, $image_data)
	{
		$image_type = wp_remote_retrieve_header($response, 'content-type');

		// Если тип не определен, используем wp_check_filetype
		if (empty($image_type) || strpos($image_type, 'image/') !== 0) {
			// Создаем временный файл для проверки
			$temp_file = wp_tempnam();
			file_put_contents($temp_file, $image_data);

			$filetype = wp_check_filetype($temp_file);
			if ($filetype['type'] && strpos($filetype['type'], 'image/') === 0) {
				$image_type = $filetype['type'];
			}

			// Удаляем временный файл
			@unlink($temp_file);
		}

		// Если все еще не определили тип, используем дефолтный
		if (empty($image_type)) {
			$image_type = 'image/jpeg';
		}

		return $image_type;
	}

	/**
	 * Получение типа генератора
	 */
	protected function get_type()
	{
		return 'image';
	}

	/**
	 * Логирование полного ответа для отладки
	 */
	private function log_full_response($generation_id, $response)
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$log_data = [
				'generation_id' => $generation_id,
				'response' => $response,
				'model' => $this->model ? $this->model->model_name : 'unknown',
				'timestamp' => current_time('mysql')
			];

			error_log('RDS AI Engine Full Response Log: ' . json_encode($log_data, JSON_PRETTY_PRINT));
		}
	}
}
