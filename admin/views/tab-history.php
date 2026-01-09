<?php

/**
 * Вкладка управления историей диалогов
 */

$main = RDS_AIE_Main::get_instance();
$history_manager = $main->get_history_manager();

// Настройки по умолчанию
$default_settings = get_option('rds_aie_default_settings', [
	'history_retention_days' => 7,
	'cleanup_enabled' => true
]);

// Обработка сохранения настроек
if (isset($_POST['action']) && $_POST['action'] === 'save_history_settings') {
	if (wp_verify_nonce($_POST['_wpnonce'], 'rds_aie_history_settings')) {
		$settings = [
			'history_retention_days' => intval($_POST['history_retention_days']),
			'cleanup_enabled' => isset($_POST['cleanup_enabled']) ? 1 : 0
		];

		update_option('rds_aie_history_settings', $settings);

		echo '<div class="notice notice-success"><p>' .
			__('Settings saved successfully.', 'rds-ai-engine') .
			'</p></div>';
	}
}

// Обработка очистки истории
if (isset($_POST['action']) && $_POST['action'] === 'cleanup_history') {
	if (wp_verify_nonce($_POST['_wpnonce'], 'rds_aie_cleanup_history')) {
		$days = intval($_POST['cleanup_days']);
		$deleted = $history_manager->cleanup_old_history($days);

		echo '<div class="notice notice-success"><p>' .
			sprintf(__('Deleted %d old conversation records.', 'rds-ai-engine'), $deleted) .
			'</p></div>';
	}
}

// Получаем текущие настройки
$current_settings = get_option('rds_aie_history_settings', $default_settings);
?>

<div class="rds-aie-history">
	<h2><?php _e('Conversation History Settings', 'rds-ai-engine'); ?></h2>

	<div class="history-settings">
		<form method="post">
			<?php wp_nonce_field('rds_aie_history_settings'); ?>
			<input type="hidden" name="action" value="save_history_settings">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="history_retention_days"><?php _e('History Retention Period', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="history_retention_days" name="history_retention_days"
							min="1" max="365" step="1"
							value="<?php echo esc_attr($current_settings['history_retention_days']); ?>">
						<p class="description"><?php _e('Number of days to keep conversation history. Older records will be automatically deleted.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cleanup_enabled"><?php _e('Automatic Cleanup', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="cleanup_enabled" name="cleanup_enabled" value="1"
								<?php checked($current_settings['cleanup_enabled']); ?>>
							<?php _e('Enable automatic cleanup of old history', 'rds-ai-engine'); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php _e('Save Settings', 'rds-ai-engine'); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="history-cleanup">
		<h3><?php _e('Manual History Cleanup', 'rds-ai-engine'); ?></h3>
		<p><?php _e('You can manually delete conversation history older than a specified number of days.', 'rds-ai-engine'); ?></p>

		<form method="post">
			<?php wp_nonce_field('rds_aie_cleanup_history'); ?>
			<input type="hidden" name="action" value="cleanup_history">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cleanup_days"><?php _e('Delete History Older Than', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="cleanup_days" name="cleanup_days"
							min="1" max="365" step="1" value="7"> <?php _e('days', 'rds-ai-engine'); ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-danger"
					onclick="return confirm('<?php _e('Are you sure you want to delete old conversation history? This action cannot be undone.', 'rds-ai-engine'); ?>');">
					<?php _e('Cleanup Old History', 'rds-ai-engine'); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="history-stats">
		<h3><?php _e('History Statistics', 'rds-ai-engine'); ?></h3>
		<?php
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_conversations';

		$total_messages = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
		$total_sessions = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$table_name}");
		$oldest_record = $wpdb->get_var("SELECT MIN(created_at) FROM {$table_name}");
		?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e('Statistic', 'rds-ai-engine'); ?></th>
					<th><?php _e('Value', 'rds-ai-engine'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php _e('Total Messages', 'rds-ai-engine'); ?></td>
					<td><?php echo esc_html($total_messages ?: 0); ?></td>
				</tr>
				<tr>
					<td><?php _e('Total Conversation Sessions', 'rds-ai-engine'); ?></td>
					<td><?php echo esc_html($total_sessions ?: 0); ?></td>
				</tr>
				<tr>
					<td><?php _e('Oldest Record', 'rds-ai-engine'); ?></td>
					<td><?php echo $oldest_record ? esc_html($oldest_record) : __('No records', 'rds-ai-engine'); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>