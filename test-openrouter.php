<?php

/**
 * Тестовый скрипт для диагностики OpenRouter
 */

require_once('wp-load.php');

// Проверяем права
if (!current_user_can('edit_posts')) {
	die('Unauthorized');
}

// Настройки для теста
$api_key = 'sk-or-v1-...'; // Замените на реальный ключ
// $model = 'google/gemini-3.1-flash-lite-image'; // Или другая image модель
// $model = 'qwen/qwen-image-3'; // Или другая image модель
$model = 'bytedance-seed/seedream-5-0-lite'; // Или другая image модель
$prompt = 'cat';

// Формируем запрос как в документации
$request_data = [
	'model' => $model,
	// "messages" => [
	// 	[
	// 		"role" => "user",
	// 		"content" => $prompt
	// 	]
	// ],
	// "modalities" => ["image"],
	// 'response_format' => "b64_json",
	// "seed" => 999999,
	'image_config' => ["aspect_ratio" => '1'],
	'prompt' => $prompt,
];

// Отправляем запрос напрямую
$response = wp_remote_post('https://openrouter.ai/api/v1/images', [
	'timeout' => 180,
	'headers' => [
		'Content-Type' => 'application/json',
		'Authorization' => 'Bearer ' . $api_key,
		'HTTP-Referer' => get_site_url(),
		'X-Title' => get_bloginfo('name')
	],
	'body' => wp_json_encode($request_data)
]);

echo '<h2>Результат теста OpenRouter</h2>';
echo '<h3>Запрос:</h3>';
echo '<pre>' . json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';

if (is_wp_error($response)) {
	echo '<h3 style="color: red;">Ошибка:</h3>';
	echo '<pre>' . $response->get_error_message() . '</pre>';
} else {
	$body = wp_remote_retrieve_body($response);
	$code = wp_remote_retrieve_response_code($response);

	echo '<h3>Код ответа: ' . $code . '</h3>';
	echo '<h3>Ответ:</h3>';
	echo '<pre>' . json_encode(json_decode($body, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';

	// Добавляем отображение изображения, если ответ успешный и содержит данные
	if ($code === 200) {
		$response_data = json_decode($body, true);

		if (isset($response_data['data']) && is_array($response_data['data']) && count($response_data['data']) > 0) {
			foreach ($response_data['data'] as $index => $image_data) {
				if (isset($image_data['b64_json'])) {
					$image_url = $image_data['b64_json'];
					$image_type = $image_data['media_type'];

					// Проверяем, является ли URL data URI (base64)
					// if (strpos($image_url, 'data:image') === 0) {
					// 	// Извлекаем base64 часть из data URI
					// 	$image_base64 = str_replace('data:image/png;base64,', '', $image_url);
					// 	$image_base64 = str_replace('data:image/jpeg;base64,', '', $image_base64);
					// 	$image_base64 = str_replace('data:image/jpg;base64,', '', $image_base64);

					// 	// Определяем тип изображения из data URI
					// 	preg_match('/^data:image\/(\w+);base64,/', $image_url, $matches);
					// 	$image_type = !empty($matches[1]) ? $matches[1] : 'jpeg';

						echo '<h3>Полученное изображение #' . ($index + 1) . ':</h3>';
						echo '<img src="data:' . $image_type . ';base64,' . $image_url . '" alt="Generated Image">';

						// // Создаем ссылку для скачивания изображения
						// echo '<div><a href="' . esc_url($image_url) . '" download="generated_image_' . $index . '.' . $image_type . '" class="button">Скачать изображение</a></div>';
					// } else {
					// 	// Если это обычный URL, а не base64
					// 	echo '<h3>Полученное изображение #' . ($index + 1) . ':</h3>';
					// 	echo '<img src="' . esc_url($image_url) . '" alt="Generated Image" style="max-width: 100%; height: auto; border: 1px solid #ccc; padding: 5px; margin: 10px 0;" />';

					// 	// Для обычного URL создаем ссылку на изображение
					// 	echo '<div><a href="' . esc_url($image_url) . '" target="_blank" class="button">Открыть изображение</a></div>';
					// }
				}
			}
		} else {
			echo '<p>В ответе нет данных изображений.</p>';
		}
	}

	// Сохраняем в лог
	error_log('OpenRouter Test Response: ' . $body);
}
