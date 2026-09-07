<?php
/**
 * Вкладка настроек RAG
 */
$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();
$embedding_models = $model_manager->get_embedding_models();

// Получаем текущие настройки
$settings = get_option('rds_aie_rag_settings', [
	'embedding_model_id' => 0,
	'embedding_dims' => 1536, // <-- Добавили значение по умолчанию
	'chunk_size' => 500,
	'overlap' => 50,
	'search_results_count' => 3,
	'hybrid_search' => 0
]);

$message = '';

if (isset($_POST['action']) && $_POST['action'] === 'save_rag_settings') {
	check_admin_referer('rds_aie_rag_nonce');
	
	$new_settings = [
		'embedding_model_id' => intval($_POST['embedding_model_id']),
		'embedding_dims' => intval($_POST['embedding_dims']), // <-- Сохраняем размерность
		'chunk_size' => intval($_POST['chunk_size']),
		'overlap' => intval($_POST['overlap']),
		'search_results_count' => intval($_POST['search_results_count']),
		'hybrid_search' => isset($_POST['hybrid_search']) ? 1 : 0
	];
	
	update_option('rds_aie_rag_settings', $new_settings);
	$message = '<div class="notice notice-success"><p>' . __('RAG settings saved successfully.', 'rds-ai-engine') . '</p></div>';
	$settings = $new_settings;
}
?>

<div class="wrap rds-aie-rag-settings">
	<h1><?php _e('RAG Settings', 'rds-ai-engine'); ?></h1>
	<?php echo $message; ?>

	<form method="post">
		<?php wp_nonce_field('rds_aie_rag_nonce'); ?>
		<input type="hidden" name="action" value="save_rag_settings">

		<table class="form-table">
			<tr>
				<th><label for="embedding_model_id"><?php _e('Embedding Model', 'rds-ai-engine'); ?></label></th>
				<td>
					<select id="embedding_model_id" name="embedding_model_id" class="regular-text" required>
						<option value=""><?php _e('-- Select Model --', 'rds-ai-engine'); ?></option>
						<?php foreach ($embedding_models as $model): ?>
							<option value="<?php echo esc_attr($model->id); ?>" <?php selected($settings['embedding_model_id'], $model->id); ?>>
								<?php echo esc_html($model->name); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php _e('Model used to generate vector embeddings.', 'rds-ai-engine'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="embedding_dims"><?php _e('Embedding Dimensions', 'rds-ai-engine'); ?></label></th>
				<td>
					<input type="number" id="embedding_dims" name="embedding_dims" min="128" max="8192" step="1" value="<?php echo esc_attr($settings['embedding_dims']); ?>">
					<p class="description"><?php _e('Vector size for the selected model (e.g., 1536 for OpenAI small, 768 for BGE).', 'rds-ai-engine'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="chunk_size"><?php _e('Chunk Size (chars)', 'rds-ai-engine'); ?></label></th>
				<td>
					<input type="number" id="chunk_size" name="chunk_size" min="100" max="2000" step="50" value="<?php echo esc_attr($settings['chunk_size']); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="overlap"><?php _e('Overlap (chars)', 'rds-ai-engine'); ?></label></th>
				<td>
					<input type="number" id="overlap" name="overlap" min="0" max="500" step="10" value="<?php echo esc_attr($settings['overlap']); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="search_results_count"><?php _e('Search Results Limit', 'rds-ai-engine'); ?></label></th>
				<td>
					<input type="number" id="search_results_count" name="search_results_count" min="1" max="10" step="1" value="<?php echo esc_attr($settings['search_results_count']); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="hybrid_search"><?php _e('Hybrid Search', 'rds-ai-engine'); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="hybrid_search" name="hybrid_search" value="1" <?php checked($settings['hybrid_search']); ?>>
						<?php _e('Enable keyword-based boosting along with vector similarity.', 'rds-ai-engine'); ?>
					</label>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php _e('Save Settings', 'rds-ai-engine'); ?></button>
		</p>
	</form>
</div>