<?php
/**
 * Вкладка управления Базой Знаний (Knowledge Base)
 */
$main = RDS_AIE_Main::get_instance();
if (!class_exists('RDS_AIE_RAG_Engine')) {
	require_once RDS_AIE_PLUGIN_DIR . 'includes/class-rag-engine.php';
}
$rag_engine = $main->get_rag_engine();
$db = $main->get_db();

$documents = $db->get_knowledge_documents();
$message = '';

// Обработка действий
if (isset($_POST['action'])) {
	check_admin_referer('rds_aie_kb_nonce');
	
	try {
		switch ($_POST['action']) {
			case 'upload_text':
				$text = sanitize_textarea_field($_POST['text_content']);
				$title = sanitize_text_field($_POST['doc_title']);
				$source = $title ? sanitize_file_name($title) : 'manual-' . time();
				
				if (!empty($text)) {
					$doc_id = $rag_engine->index_text($text, $source, $title);
					if ($doc_id) {
						$message = '<div class="notice notice-success"><p>' . __('Text indexed successfully.', 'rds-ai-engine') . '</p></div>';
						$documents = $db->get_knowledge_documents();
					}
				}
				break;
			
			case 'delete_document':
				$doc_id = sanitize_text_field($_POST['document_id']);
				$db->delete_knowledge_document($doc_id);
				$message = '<div class="notice notice-success"><p>' . __('Document deleted from Knowledge Base.', 'rds-ai-engine') . '</p></div>';
				$documents = $db->get_knowledge_documents();
				break;
				
			case 'clear_all':
				$db->clear_knowledge_base();
				$message = '<div class="notice notice-warning"><p>' . __('Knowledge Base cleared.', 'rds-ai-engine') . '</p></div>';
				$documents = [];
				break;
		}
	} catch (Exception $e) {
		$message = '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
	}
}
?>

<div class="wrap rds-aie-kb">
	<h1><?php _e('Knowledge Base', 'rds-ai-engine'); ?></h1>
	<?php echo $message; ?>

	<div class="kb-upload">
		<h2><?php _e('Add New Document', 'rds-ai-engine'); ?></h2>
		<form method="post">
			<?php wp_nonce_field('rds_aie_kb_nonce'); ?>
			<input type="hidden" name="action" value="upload_text">
			
			<table class="form-table">
				<tr>
					<th><label for="doc_title"><?php _e('Document Title', 'rds-ai-engine'); ?></label></th>
					<td>
						<input type="text" id="doc_title" name="doc_title" class="regular-text" placeholder="e.g., Company Policy 2026">
					</td>
				</tr>
				<tr>
					<th><label for="text_content"><?php _e('Content', 'rds-ai-engine'); ?></label></th>
					<td>
						<textarea id="text_content" name="text_content" rows="10" class="large-text" required></textarea>
						<p class="description"><?php _e('Paste text here. It will be split into chunks and indexed.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php _e('Index Document', 'rds-ai-engine'); ?></button>
			</p>
		</form>
	</div>

	<hr>

	<div class="kb-list">
		<h2><?php _e('Indexed Documents', 'rds-ai-engine'); ?></h2>
		
		<?php if (empty($documents)): ?>
			<p><?php _e('No documents in the Knowledge Base yet.', 'rds-ai-engine'); ?></p>
		<?php else: ?>
			<form method="post" style="display:inline-block; margin-bottom: 10px;">
				<?php wp_nonce_field('rds_aie_kb_nonce'); ?>
				<input type="hidden" name="action" value="clear_all">
				<button type="submit" class="button button-small button-link-delete" onclick="return confirm('Delete ALL documents?');"><?php _e('Clear All', 'rds-ai-engine'); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Title / Source', 'rds-ai-engine'); ?></th>
						<th><?php _e('Chunks', 'rds-ai-engine'); ?></th>
						<th><?php _e('Last Updated', 'rds-ai-engine'); ?></th>
						<th><?php _e('Actions', 'rds-ai-engine'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($documents as $doc): ?>
						<tr>
							<td>
								<strong><?php echo esc_html($doc->source_name); ?></strong>
								<?php if ($doc->title): ?>
									<br><small><?php echo esc_html($doc->title); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($doc->chunk_count); ?></td>
							<td><?php echo esc_html(date('Y-m-d H:i', strtotime($doc->last_updated))); ?></td>
							<td>
								<form method="post" style="display:inline;" onsubmit="return confirm('Delete this document?');">
									<?php wp_nonce_field('rds_aie_kb_nonce'); ?>
									<input type="hidden" name="action" value="delete_document">
									<input type="hidden" name="document_id" value="<?php echo esc_attr($doc->document_id); ?>">
									<button type="submit" class="button button-small button-link-delete"><?php _e('Delete', 'rds-ai-engine'); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>