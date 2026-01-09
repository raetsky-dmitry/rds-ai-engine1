<?php

/**
 * Удаление плагина RDS AI Engine
 */

// Если файл вызывается напрямую, прерываем выполнение
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Удаление таблиц БД
global $wpdb;
$table_prefix = $wpdb->prefix . 'rds_aie_';
$tables = [
	'assistants',
	'models'
];

foreach ($tables as $table) {
	$wpdb->query("DROP TABLE IF EXISTS {$table_prefix}{$table}");
}

// Удаление опций
delete_option('rds_aie_version');
delete_option('rds_aie_default_settings');

// Удаление кеша
wp_cache_flush();
