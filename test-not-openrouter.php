<?php

/**
 * Тестовый скрипт для диагностики OpenRouter
 */

require_once('../../../wp-load.php');

// Проверяем права
if (!current_user_can('edit_posts')) {
	die('Unauthorized');
}

// Настройки для теста
$api_key = 'yhqT2Y8XgCxMLELfu2iQU8WXGQE/fMbiVjIVHCEn4rYpiWBTDeKnuwcn/mgvpmxI55qLiyJVpToOanXOtMmnfwLieNGRnct8hivYHdDKAvhsyIr75TNUJXWIRK58+JsnQ=='; // Замените на реальный ключ
$model = 'flux'; // Или другая image модель
$prompt = 'A cute cartoon cat';

// Формируем запрос как в документации
$request_data = [
	'model' => 'flux',
	'prompt' => $prompt,
	'size' => "1024x700",
	'response_format' => "b64_json",
	'extra_body' => ["seed" => 42, "nologo" => True]
];

// Отправляем запрос напрямую
$response = wp_remote_post('https://api.llm7.io/v1/images/generations', [
	'timeout' => 60,
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
					$image_base64 = $image_data['b64_json'];

					echo '<h3>Полученное изображение #' . ($index + 1) . ':</h3>';
					echo '<img src="data:image/jpeg;base64,' . esc_attr($image_base64) . '" alt="Generated Image" style="max-width: 100%; height: auto; border: 1px solid #ccc; padding: 5px; margin: 10px 0;" />';

					// Также можно добавить кнопку для скачивания изображения
					echo '<div><a href="data:image/jpeg;base64,' . esc_attr($image_base64) . '" download="generated_image_' . $index . '.jpg" class="button">Скачать изображение</a></div>';
				}
			}
		} else {
			echo '<p>В ответе нет данных изображений.</p>';
		}
	}

	// Сохраняем в лог
	error_log('OpenRouter Test Response: ' . $body);
}
