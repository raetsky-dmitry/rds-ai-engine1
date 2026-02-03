<?php

/**
 * Plugin Name: RDS AI Engine
 * Plugin URI: https://github.com/your-username/rds-ai-engine
 * Description: Базовый плагин для интеграции с ИИ в WordPress. Предоставляет управление моделями, ассистентами, базой знаний и историей диалогов.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: rds-ai-engine
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Если файл вызывается напрямую, прерываем выполнение
if (!defined('ABSPATH')) {
	exit;
}

// Константы плагина
define('RDS_AIE_VERSION', '1.0.0');
define('RDS_AIE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RDS_AIE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RDS_AIE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Константы для типовых ассистентов
define('RDS_AIE_ASSISTANT_GENERAL', 0);
define('RDS_AIE_ASSISTANT_CREATIVE', 1);
define('RDS_AIE_ASSISTANT_TECHNICAL', 2);
define('RDS_AIE_ASSISTANT_SUPPORT', 3);

// Вспомогательные функции
if (!function_exists('rds_aie_create_assistant')) {
	function rds_aie_create_assistant($name, $system_prompt, $args = [])
	{
		$ai_engine = RDS_AIE_Main::get_instance();
		return $ai_engine->create_assistant(array_merge([
			'name' => $name,
			'system_prompt' => $system_prompt
		], $args));
	}
}

if (!function_exists('rds_aie_get_assistant')) {
	function rds_aie_get_assistant($assistant_id)
	{
		$ai_engine = RDS_AIE_Main::get_instance();
		return $ai_engine->get_assistant($assistant_id);
	}
}

if (!function_exists('rds_aie_chat')) {
	function rds_aie_chat($message, $assistant_id = 0, $args = [])
	{
		$ai_engine = RDS_AIE_Main::get_instance();
		return $ai_engine->chat_completion(array_merge([
			'assistant_id' => $assistant_id,
			'message' => $message
		], $args));
	}
}

// Простой автозагрузчик классов
function rds_aie_autoloader($class_name)
{
	// Наш префикс
	$prefix = 'RDS_AIE_';

	// Проверяем, относится ли класс к нашему плагину
	$len = strlen($prefix);
	if (strncmp($prefix, $class_name, $len) !== 0) {
		return;
	}

	// Получаем относительное имя класса
	$relative_class = substr($class_name, $len);

	// Формируем путь к файлу
	$file = RDS_AIE_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

	// Если файл существует, подключаем его
	if (file_exists($file)) {
		require_once $file;
	} else {
		// Для отладки
		error_log("RDS AI Engine: Class file not found: $file for class $class_name");
	}
}

// Регистрируем автозагрузчик
spl_autoload_register('rds_aie_autoloader');

// Проверяем, что все основные классы загружены
function rds_aie_check_classes()
{
	$required_classes = [
		'RDS_AIE_Main',
		'RDS_AIE_DB',
		'RDS_AIE_Model_Manager',
		'RDS_AIE_Assistant_Manager',
		'RDS_AIE_AI_Client',
		'RDS_AIE_History_Manager',
		'RDS_AIE_Generator_Base',
		'RDS_AIE_Text_Generator',
		'RDS_AIE_Image_Generator',
		'RDS_AIE_Generator_Factory'
	];

	foreach ($required_classes as $class) {
		if (!class_exists($class)) {
			error_log("RDS AI Engine: Required class $class not found");
			return false;
		}
	}
	return true;
}

// Инициализация плагина
function rds_aie_init()
{
	// Проверяем наличие классов
	if (!rds_aie_check_classes()) {
		add_action('admin_notices', function () {
?>
			<div class="notice notice-error">
				<p><strong>RDS AI Engine:</strong> Не удалось загрузить необходимые классы. Проверьте наличие файлов в папке includes/.</p>
			</div>
<?php
		});
		return;
	}

	// Загрузка текстового домена
	load_plugin_textdomain('rds-ai-engine', false, dirname(RDS_AIE_PLUGIN_BASENAME) . '/languages');

	// Инициализация главного класса
	$GLOBALS['rds_aie'] = RDS_AIE_Main::get_instance();
	
	// Добавляем хук для автоматической очистки
	if (!wp_next_scheduled('rds_aie_cleanup_generations')) {
		wp_schedule_event(time(), 'hourly', 'rds_aie_cleanup_generations');
	}
	
	// Хук для выполнения очистки
	add_action('rds_aie_cleanup_generations', 'rds_aie_perform_cleanup_generations');
}
add_action('plugins_loaded', 'rds_aie_init');

// Хуки активации и деактивации
register_activation_hook(__FILE__, 'rds_aie_activate_plugin');
register_deactivation_hook(__FILE__, 'rds_aie_deactivate_plugin');

function rds_aie_activate_plugin()
{
	// Сначала загружаем класс DB
	rds_aie_autoloader('RDS_AIE_DB');

	if (class_exists('RDS_AIE_DB')) {
		$db = new RDS_AIE_DB();
		$db->create_tables();

		// Добавляем опции по умолчанию
		add_option('rds_aie_version', RDS_AIE_VERSION);
		add_option('rds_aie_default_settings', [
			'max_tokens' => 1000,
			'temperature' => 0.7,
			'history_enabled' => true,
			'history_messages_count' => 10
		]);

		// Настройки изображений по умолчанию
		add_option('rds_aie_image_defaults', [
			'size' => '1024x1024',
			'quality' => 'standard',
			'style' => 'vivid',
			'n' => 1,
			'response_format' => 'b64_json'
		]);
	}
}

function rds_aie_deactivate_plugin()
{
	// Очистка кеша, остановка крона и т.д.
	wp_clear_scheduled_hook('rds_aie_cleanup_history');
	wp_clear_scheduled_hook('rds_aie_cleanup_generations');
}

// Хук для удаления плагина
register_uninstall_hook(__FILE__, 'rds_aie_uninstall_plugin');

function rds_aie_uninstall_plugin()
{
	// Загружаем класс DB
	rds_aie_autoloader('RDS_AIE_DB');

	if (class_exists('RDS_AIE_DB')) {
		// Удаление таблиц БД
		$db = new RDS_AIE_DB();
		$db->drop_tables();
	}

	// Удаление опций
	delete_option('rds_aie_version');
	delete_option('rds_aie_default_settings');
}


// Хук для обновления плагина
add_action('plugins_loaded', 'rds_aie_check_update');

function rds_aie_check_update()
{
	$current_version = get_option('rds_aie_version', '0');

	if (version_compare($current_version, RDS_AIE_VERSION, '<')) {
		// Обновляем таблицы
		rds_aie_autoloader('RDS_AIE_DB');

		if (class_exists('RDS_AIE_DB')) {
			$db = new RDS_AIE_DB();
			$db->update_tables();
		}

		// Обновляем версию
		update_option('rds_aie_version', RDS_AIE_VERSION);
	}
}

/**
 * Генерация изображения
 * 
 * @param string $prompt Текст промпта
 * @param array $params Параметры генерации
 * @return array|WP_Error Массив base64 изображений или ошибка
 */
if (!function_exists('rds_aie_generate_image')) {
	function rds_aie_generate_image($prompt, $params = [])
	{
		$ai_engine = RDS_AIE_Main::get_instance();

		$defaults = [
			'model_id' => 0,
			'session_id' => '',
			'plugin_id' => 'default'
		];

		$params = wp_parse_args($params, $defaults);
		$params['prompt'] = $prompt;

		try {
			return $ai_engine->image_generation($params);
		} catch (Exception $e) {
			return new WP_Error('image_generation_error', $e->getMessage());
		}
	}
}

/**
 * Универсальная генерация
 * 
 * @param mixed $input Входные данные (промпт для изображения или сообщение для чата)
 * @param array $params Параметры генерации
 * @return mixed Результат генерации
 */
if (!function_exists('rds_aie_generate')) {
	function rds_aie_generate($input, $params = [])
	{
		$ai_engine = RDS_AIE_Main::get_instance();

		$defaults = [
			'type' => 'text',
			'model_id' => 0,
			'session_id' => '',
			'plugin_id' => 'default'
		];

		$params = wp_parse_args($params, $defaults);

		// Определяем тип по параметрам
		if (isset($params['prompt'])) {
			$params['type'] = 'image';
		} elseif (isset($params['message'])) {
			$params['type'] = 'text';
			$params['message'] = $input;
		} else {
			// Если тип не указан явно, пытаемся определить
			if ($params['type'] === 'image') {
				$params['prompt'] = $input;
			} else {
				$params['message'] = $input;
			}
		}

		try {
			return $ai_engine->generate($params);
		} catch (Exception $e) {
			return new WP_Error('generation_error', $e->getMessage());
		}
	}
}

/**
 * Получение моделей определённого типа
 * 
 * @param string $type Тип модели (text, image, both)
 * @return array Массив моделей
 */
if (!function_exists('rds_aie_get_models_by_type')) {
	function rds_aie_get_models_by_type($type = 'text')
	{
		$ai_engine = RDS_AIE_Main::get_instance();
		$model_manager = $ai_engine->get_model_manager();

		try {
			return $model_manager->get_models_by_type($type);
		} catch (Exception $e) {
			return [];
		}
	}
}

/**
 * Получение модели по умолчанию для указанного типа
 * 
 * @param string $type Тип модели (text, image, both)
 * @return object|null Объект модели или null
 */
if (!function_exists('rds_aie_get_default_model_by_type')) {
	function rds_aie_get_default_model_by_type($type = 'text')
	{
		$ai_engine = RDS_AIE_Main::get_instance();
		$model_manager = $ai_engine->get_model_manager();

		try {
			return $model_manager->get_default_model_by_type($type);
		} catch (Exception $e) {
			return null;
		}
	}
}

/**
 * Выполнение очистки старых записей генерации изображений
 */
function rds_aie_perform_cleanup_generations() {
    error_log('RDS AI Engine: Automatic cleanup triggered');
    
    // Проверяем, включена ли автоматическая очистка
    $settings = get_option('rds_aie_history_settings', []);
    
    error_log('RDS AI Engine: Settings retrieved: ' . print_r($settings, true));
    
    if (isset($settings['cleanup_enabled']) && $settings['cleanup_enabled']) {
        $hours = isset($settings['image_generation_retention_hours']) ? 
            intval($settings['image_generation_retention_hours']) : 1;
        
        error_log("RDS AI Engine: Cleaning up images older than $hours hours");
            
        if (class_exists('RDS_AIE_Main')) {
            $ai_engine = RDS_AIE_Main::get_instance();
            $history_manager = $ai_engine->get_history_manager();
            
            if ($history_manager) {
                $result = $history_manager->cleanup_old_generations($hours);
                error_log("RDS AI Engine: Cleaned up $result image generation records");
            } else {
                error_log("RDS AI Engine: Could not get history manager instance");
            }
        } else {
            error_log("RDS AI Engine: Main class does not exist");
        }
    } else {
        error_log("RDS AI Engine: Automatic cleanup is disabled");
    }
}

// Вспомогательная функция для тестирования URL
if (!function_exists('rds_aie_test_api_url')) {
	function rds_aie_test_api_url($url, $api_key = '')
	{
		$test_url = trailingslashit($url) . 'images/generations';

		$args = [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key
			],
			'body' => wp_json_encode([
				'model' => 'dall-e-2',
				'prompt' => 'test',
				'n' => 1,
				'size' => '256x256'
			])
		];

		$response = wp_remote_post($test_url, $args);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
				'code' => 0
			];
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		return [
			'success' => $code >= 200 && $code < 300,
			'message' => $body,
			'code' => $code
		];
	}
}
