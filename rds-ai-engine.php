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
		'RDS_AIE_AI_Client'
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
	}
}

function rds_aie_deactivate_plugin()
{
	// Очистка кеша, остановка крона и т.д.
	wp_clear_scheduled_hook('rds_aie_cleanup_history');
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
