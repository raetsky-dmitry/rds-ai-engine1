<?php

/**
 * Вкладка управления ассистентами
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

	if (wp_verify_nonce($nonce, 'rds_aie_assistants')) {
		try {
			switch ($action) {
				case 'save_assistant':
					$assistant_data = [
						'id' => isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0,
						'name' => sanitize_text_field($_POST['name']),
						'system_prompt' => sanitize_textarea_field($_POST['system_prompt']),
						'default_model_id' => !empty($_POST['default_model_id']) ? intval($_POST['default_model_id']) : null,
						'max_tokens' => intval($_POST['max_tokens']),
						'temperature' => floatval($_POST['temperature']),
						'history_enabled' => isset($_POST['history_enabled']) ? 1 : 0,
						'history_messages_count' => intval($_POST['history_messages_count']),
						'knowledge_base_enabled' => isset($_POST['knowledge_base_enabled']) ? 1 : 0
					];

					$assistant_manager->save($assistant_data);
					echo '<div class="notice notice-success"><p>' .
						__('Assistant saved successfully.', 'rds-ai-engine') .
						'</p></div>';
					break;

				case 'delete_assistant':
					$assistant_id = intval($_POST['assistant_id']);
					$assistant_manager->delete($assistant_id);
					echo '<div class="notice notice-success"><p>' .
						__('Assistant deleted successfully.', 'rds-ai-engine') .
						'</p></div>';
					break;
			}

			// Обновляем список ассистентов
			$assistants = $assistant_manager->get_all();
		} catch (Exception $e) {
			echo '<div class="notice notice-error"><p>' .
				esc_html($e->getMessage()) .
				'</p></div>';
		}
	}
}

// Получение данных ассистента для редактирования
$edit_assistant = null;
if (isset($_GET['edit'])) {
	$edit_assistant = $assistant_manager->get(intval($_GET['edit']));
}

// Получение ассистента для удаления
$delete_assistant = null;
if (isset($_GET['delete'])) {
	$delete_assistant = $assistant_manager->get(intval($_GET['delete']));
}
?>

<div class="rds-aie-assistants">
	<?php if ($delete_assistant): ?>
		<!-- Форма подтверждения удаления -->
		<div class="confirm-delete">
			<h2><?php _e('Confirm Delete', 'rds-ai-engine'); ?></h2>
			<p><?php printf(
					__('Are you sure you want to delete assistant "%s"?', 'rds-ai-engine'),
					esc_html($delete_assistant->name)
				); ?></p>
			<form method="post">
				<?php wp_nonce_field('rds_aie_assistants'); ?>
				<input type="hidden" name="action" value="delete_assistant">
				<input type="hidden" name="assistant_id" value="<?php echo esc_attr($delete_assistant->id); ?>">
				<button type="submit" class="button button-danger"><?php _e('Delete', 'rds-ai-engine'); ?></button>
				<a href="?page=rds-aie&tab=assistants" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
			</form>
		</div>
	<?php endif; ?>

	<!-- Форма добавления/редактирования ассистента -->
	<div class="assistant-form">
		<h2><?php echo $edit_assistant ? __('Edit Assistant', 'rds-ai-engine') : __('Add New Assistant', 'rds-ai-engine'); ?></h2>
		<form method="post">
			<?php wp_nonce_field('rds_aie_assistants'); ?>
			<input type="hidden" name="action" value="save_assistant">
			<input type="hidden" name="assistant_id" value="<?php echo $edit_assistant ? esc_attr($edit_assistant->id) : 0; ?>">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="name"><?php _e('Assistant Name', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<input type="text" id="name" name="name" class="regular-text"
							value="<?php echo $edit_assistant ? esc_attr($edit_assistant->name) : ''; ?>" required>
						<p class="description"><?php _e('A descriptive name for this assistant.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="system_prompt"><?php _e('System Prompt', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<textarea id="system_prompt" name="system_prompt" rows="5" class="large-text" required><?php
																												echo $edit_assistant ? esc_textarea($edit_assistant->system_prompt) : '';
																												?></textarea>
						<p class="description"><?php _e('Instructions that define the assistant\'s behavior and personality.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="default_model_id"><?php _e('Default Model', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="default_model_id" name="default_model_id" class="regular-text">
							<option value=""><?php _e('-- Select Model --', 'rds-ai-engine'); ?></option>
							<?php foreach ($models as $model): ?>
								<option value="<?php echo esc_attr($model->id); ?>"
									<?php selected($edit_assistant ? $edit_assistant->default_model_id : 0, $model->id); ?>>
									<?php echo esc_html($model->name); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e('Default AI model for this assistant.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="max_tokens"><?php _e('Max Response Tokens', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="max_tokens" name="max_tokens" min="1" max="32000" step="1"
							value="<?php echo $edit_assistant ? esc_attr($edit_assistant->max_tokens) : 1000; ?>">
						<p class="description"><?php _e('Maximum tokens in the assistant\'s response.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="temperature"><?php _e('Temperature', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="temperature" name="temperature" min="0" max="2" step="0.1"
							value="<?php echo $edit_assistant ? esc_attr($edit_assistant->temperature) : 0.7; ?>">
						<p class="description"><?php _e('Controls randomness: lower = more focused, higher = more creative.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="history_enabled"><?php _e('Enable History', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="history_enabled" name="history_enabled" value="1"
								<?php checked($edit_assistant ? $edit_assistant->history_enabled : 1); ?>>
							<?php _e('Keep conversation history', 'rds-ai-engine'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="history_messages_count"><?php _e('History Messages Count', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="history_messages_count" name="history_messages_count" min="1" max="100" step="1"
							value="<?php echo $edit_assistant ? esc_attr($edit_assistant->history_messages_count) : 10; ?>">
						<p class="description"><?php _e('Number of previous messages to include in context.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="knowledge_base_enabled"><?php _e('Enable Knowledge Base', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="knowledge_base_enabled" name="knowledge_base_enabled" value="1"
								<?php checked($edit_assistant ? $edit_assistant->knowledge_base_enabled : 0); ?>>
							<?php _e('Use knowledge base for responses', 'rds-ai-engine'); ?>
						</label>
						<p class="description"><?php _e('(Feature will be available in the next update)', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $edit_assistant ? __('Update Assistant', 'rds-ai-engine') : __('Add Assistant', 'rds-ai-engine'); ?>
				</button>
				<?php if ($edit_assistant): ?>
					<a href="?page=rds-aie&tab=assistants" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<!-- Список ассистентов -->
	<div class="assistants-list">
		<h2><?php _e('Available Assistants', 'rds-ai-engine'); ?></h2>

		<?php if (empty($assistants)): ?>
			<p><?php _e('No assistants created yet.', 'rds-ai-engine'); ?></p>
		<?php else: ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Name', 'rds-ai-engine'); ?></th>
						<th><?php _e('Default Model', 'rds-ai-engine'); ?></th>
						<th><?php _e('Max Tokens', 'rds-ai-engine'); ?></th>
						<th><?php _e('Temperature', 'rds-ai-engine'); ?></th>
						<th><?php _e('History', 'rds-ai-engine'); ?></th>
						<th><?php _e('Actions', 'rds-ai-engine'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($assistants as $assistant): ?>
						<tr>
							<td><?php echo esc_html($assistant->name); ?></td>
							<td><?php echo esc_html($assistant->model_name ?: '—'); ?></td>
							<td><?php echo esc_html($assistant->max_tokens); ?></td>
							<td><?php echo esc_html($assistant->temperature); ?></td>
							<td>
								<?php if ($assistant->history_enabled): ?>
									<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
									(<?php echo esc_html($assistant->history_messages_count); ?>)
								<?php else: ?>
									<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="?page=rds-aie&tab=assistants&edit=<?php echo esc_attr($assistant->id); ?>"
									class="button button-small">
									<?php _e('Edit', 'rds-ai-engine'); ?>
								</a>
								<a href="?page=rds-aie&tab=assistants&delete=<?php echo esc_attr($assistant->id); ?>"
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