<?php

/**
 * Вкладка диагностики и отладки
 */

$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();

// Получаем все модели
$models = $model_manager->get_all();

// Информация о сервере
$server_info = [
	'PHP Version' => phpversion(),
	'WordPress Version' => get_bloginfo('version'),
	'cURL Enabled' => function_exists('curl_init') ? 'Yes' : 'No',
	'OpenSSL Version' => OPENSSL_VERSION_TEXT,
	'Max Execution Time' => ini_get('max_execution_time'),
	'Memory Limit' => ini_get('memory_limit')
];

// Проверка соединения с моделями
if (isset($_GET['test_connection']) && isset($_GET['model_id'])) {
	$model_id = intval($_GET['model_id']);
	$model = $model_manager->get($model_id);

	if ($model) {
		$ai_client = $main->get_ai_client();

		if ($model->model_type === 'image' || $model->model_type === 'both') {
			$test_result = $ai_client->test_image_api_connection(
				$model->base_url,
				$model->api_key,
				$model->model_name
			);
		} else {
			$test_result = $ai_client->test_connection(
				$model->base_url,
				$model->api_key,
				$model->model_name
			);
		}
	}
}
?>

<div class="rds-aie-debug">
	<h2><?php _e('Diagnostics and Debug', 'rds-ai-engine'); ?></h2>

	<?php if (isset($test_result)): ?>
		<div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>">
			<p><strong><?php echo $test_result['success'] ? __('Success:', 'rds-ai-engine') : __('Error:', 'rds-ai-engine'); ?></strong>
				<?php echo esc_html($test_result['message']); ?></p>
		</div>
	<?php endif; ?>

	<div class="server-info">
		<h3><?php _e('Server Information', 'rds-ai-engine'); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e('Parameter', 'rds-ai-engine'); ?></th>
					<th><?php _e('Value', 'rds-ai-engine'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($server_info as $key => $value): ?>
					<tr>
						<td><?php echo esc_html($key); ?></td>
						<td><?php echo esc_html($value); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="models-test">
		<h3><?php _e('Test API Connections', 'rds-ai-engine'); ?></h3>

		<?php if (empty($models)): ?>
			<p><?php _e('No models configured.', 'rds-ai-engine'); ?></p>
		<?php else: ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Model', 'rds-ai-engine'); ?></th>
						<th><?php _e('Type', 'rds-ai-engine'); ?></th>
						<th><?php _e('API URL', 'rds-ai-engine'); ?></th>
						<th><?php _e('Actions', 'rds-ai-engine'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($models as $model): ?>
						<tr>
							<td><?php echo esc_html($model->name); ?></td>
							<td>
								<span class="model-type-badge model-type-<?php echo esc_attr($model->model_type); ?>">
									<?php
									$type_labels = [
										'text' => __('Text', 'rds-ai-engine'),
										'image' => __('Image', 'rds-ai-engine'),
										'both' => __('Both', 'rds-ai-engine')
									];
									echo esc_html($type_labels[$model->model_type] ?? $model->model_type);
									?>
								</span>
							</td>
							<td><?php echo esc_html($model->base_url); ?></td>
							<td>
								<a href="?page=rds-aie&tab=debug&test_connection=1&model_id=<?php echo esc_attr($model->id); ?>"
									class="button button-small">
									<?php _e('Test Connection', 'rds-ai-engine'); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="debug-tips">
		<h3><?php _e('Troubleshooting Tips', 'rds-ai-engine'); ?></h3>
		<ol>
			<li><?php _e('Check if the API URL is correct for image generation (should be like https://api.openai.com/v1/)', 'rds-ai-engine'); ?></li>
			<li><?php _e('Verify that your API key has permissions for image generation', 'rds-ai-engine'); ?></li>
			<li><?php _e('Check if the model name supports image generation (e.g., dall-e-2, dall-e-3)', 'rds-ai-engine'); ?></li>
			<li><?php _e('Make sure your server can make outgoing HTTPS requests', 'rds-ai-engine'); ?></li>
			<li><?php _e('Check the error logs for more detailed information', 'rds-ai-engine'); ?></li>
		</ol>
	</div>
</div>