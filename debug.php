<?php

/**
 * Временный файл для отладки
 */

// Временная замена main.php для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Добавьте это в начало файла rds-ai-engine.php после определения констант:
add_action('init', function () {
	if (current_user_can('administrator') && isset($_GET['debug_rds_aie'])) {
		echo '<pre>';
		var_dump([
			'RDS_AIE_PLUGIN_DIR' => RDS_AIE_PLUGIN_DIR,
			'class_exists Main' => class_exists('RDS_AIE_Main'),
			'class_exists DB' => class_exists('RDS_AIE_DB'),
			'files_exist' => [
				'main' => file_exists(RDS_AIE_PLUGIN_DIR . 'includes/class-main.php'),
				'db' => file_exists(RDS_AIE_PLUGIN_DIR . 'includes/class-db.php'),
				'model_manager' => file_exists(RDS_AIE_PLUGIN_DIR . 'includes/class-model-manager.php'),
				'assistant_manager' => file_exists(RDS_AIE_PLUGIN_DIR . 'includes/class-assistant-manager.php'),
				'ai_client' => file_exists(RDS_AIE_PLUGIN_DIR . 'includes/class-ai-client.php')
			]
		]);
		echo '</pre>';
		exit;
	}
});
