<?php

/**
 * Вкладка управления моделями
 */

// Получаем экземпляр плагина
$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();
$assistant_manager = $main->get_assistant_manager();

// Проверяем, что менеджер инициализирован
if (!$model_manager) {
	echo '<div class="notice notice-error"><p>' .
		__('Model manager is not initialized.', 'rds-ai-engine') .
		'</p></div>';
	return;
}

$models = $model_manager->get_all();
$assistants = $assistant_manager->get_all();

// Обработка действий
if (isset($_POST['action'])) {
	$action = sanitize_key($_POST['action']);
	$nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';

	if (wp_verify_nonce($nonce, 'rds_aie_models')) {
		try {
			switch ($action) {
				case 'save_model':
					$model_data = [
						'id' => isset($_POST['model_id']) ? intval($_POST['model_id']) : 0,
						'name' => sanitize_text_field($_POST['name']),
						'base_url' => esc_url_raw($_POST['base_url']),
						'model_name' => sanitize_text_field($_POST['model_name']),
						'api_key' => sanitize_text_field($_POST['api_key']),
						'max_tokens' => intval($_POST['max_tokens']),
						'is_default' => isset($_POST['is_default']) ? 1 : 0
					];

					$model_manager->save($model_data);
					echo '<div class="notice notice-success"><p>' .
						__('Model saved successfully.', 'rds-ai-engine') .
						'</p></div>';
					break;

				case 'delete_model':
					$model_id = intval($_POST['model_id']);
					$model_manager->delete($model_id);
					echo '<div class="notice notice-success"><p>' .
						__('Model deleted successfully.', 'rds-ai-engine') .
						'</p></div>';
					break;
			}

			// Обновляем список моделей
			$models = $model_manager->get_all();
		} catch (Exception $e) {
			echo '<div class="notice notice-error"><p>' .
				esc_html($e->getMessage()) .
				'</p></div>';
		}
	}
}

// Получение данных модели для редактирования
$edit_model = null;
if (isset($_GET['edit'])) {
	$edit_model = $model_manager->get(intval($_GET['edit']));
}

// Получение модели для удаления
$delete_model = null;
if (isset($_GET['delete'])) {
	$delete_model = $model_manager->get(intval($_GET['delete']));
}
?>

<div class="rds-aie-models">
	<?php if ($delete_model): ?>
		<!-- Форма подтверждения удаления -->
		<div class="confirm-delete">
			<h2><?php _e('Confirm Delete', 'rds-ai-engine'); ?></h2>
			<p><?php printf(
					__('Are you sure you want to delete model "%s"?', 'rds-ai-engine'),
					esc_html($delete_model->name)
				); ?></p>
			<form method="post">
				<?php wp_nonce_field('rds_aie_models'); ?>
				<input type="hidden" name="action" value="delete_model">
				<input type="hidden" name="model_id" value="<?php echo esc_attr($delete_model->id); ?>">
				<button type="submit" class="button button-danger"><?php _e('Delete', 'rds-ai-engine'); ?></button>
				<a href="?page=rds-aie&tab=models" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
			</form>
		</div>
	<?php endif; ?>

	<!-- Форма добавления/редактирования модели -->
	<div class="model-form">
		<h2><?php echo $edit_model ? __('Edit Model', 'rds-ai-engine') : __('Add New Model', 'rds-ai-engine'); ?></h2>
		<form method="post">
			<?php wp_nonce_field('rds_aie_models'); ?>
			<input type="hidden" name="action" value="save_model">
			<input type="hidden" name="model_id" value="<?php echo $edit_model ? esc_attr($edit_model->id) : 0; ?>">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php _e('Model Name', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<input type="text" id="name" name="name" class="regular-text"
							value="<?php echo $edit_model ? esc_attr($edit_model->name) : ''; ?>" required>
						<p class="description"><?php _e('A descriptive name for this AI model configuration.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="base_url"><?php _e('Base URL', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<input type="url" id="base_url" name="base_url" class="regular-text"
							value="<?php echo $edit_model ? esc_attr($edit_model->base_url) : 'https://api.openai.com/v1'; ?>" required>
						<p class="description"><?php _e('Base URL for the OpenAI-compatible API.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="model_name"><?php _e('AI Model Name', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<input type="text" id="model_name" name="model_name" class="regular-text"
							value="<?php echo $edit_model ? esc_attr($edit_model->model_name) : 'gpt-3.5-turbo'; ?>" required>
						<p class="description"><?php _e('The specific model name (e.g., gpt-3.5-turbo, gpt-4).', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_key"><?php _e('API Key', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<input type="password" id="api_key" name="api_key" class="regular-text"
							value="<?php echo $edit_model ? esc_attr($edit_model->api_key) : ''; ?>" required>
						<p class="description"><?php _e('API key for authentication.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="max_tokens"><?php _e('Max Tokens', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="max_tokens" name="max_tokens" min="1" max="32000" step="1"
							value="<?php echo $edit_model ? esc_attr($edit_model->max_tokens) : 4096; ?>">
						<p class="description"><?php _e('Maximum number of tokens in the response.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="is_default"><?php _e('Default Model', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="is_default" name="is_default" value="1"
								<?php checked($edit_model ? $edit_model->is_default : 0); ?>>
							<?php _e('Set as default model', 'rds-ai-engine'); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $edit_model ? __('Update Model', 'rds-ai-engine') : __('Add Model', 'rds-ai-engine'); ?>
				</button>
				<?php if ($edit_model): ?>
					<a href="?page=rds-aie&tab=models" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<!-- Список моделей -->
	<div class="models-list">
		<h2><?php _e('Available Models', 'rds-ai-engine'); ?></h2>

		<?php if (empty($models)): ?>
			<p><?php _e('No models configured yet.', 'rds-ai-engine'); ?></p>
		<?php else: ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Name', 'rds-ai-engine'); ?></th>
						<th><?php _e('Base URL', 'rds-ai-engine'); ?></th>
						<th><?php _e('Model', 'rds-ai-engine'); ?></th>
						<th><?php _e('Max Tokens', 'rds-ai-engine'); ?></th>
						<th><?php _e('Default', 'rds-ai-engine'); ?></th>
						<th><?php _e('Actions', 'rds-ai-engine'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($models as $model): ?>
						<tr>
							<td><?php echo esc_html($model->name); ?></td>
							<td><?php echo esc_html($model->base_url); ?></td>
							<td><?php echo esc_html($model->model_name); ?></td>
							<td><?php echo esc_html($model->max_tokens); ?></td>
							<td>
								<?php if ($model->is_default): ?>
									<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="?page=rds-aie&tab=models&edit=<?php echo esc_attr($model->id); ?>"
									class="button button-small">
									<?php _e('Edit', 'rds-ai-engine'); ?>
								</a>
								<a href="?page=rds-aie&tab=models&delete=<?php echo esc_attr($model->id); ?>"
									class="button button-small button-link-delete">
									<?php _e('Delete', 'rds-ai-engine'); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>