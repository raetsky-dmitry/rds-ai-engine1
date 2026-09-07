<?php
$main = RDS_AIE_Main::get_instance();
$agent_manager = $main->get_agent_manager();
$agents = $agent_manager->get_all();
$result = '';

if (isset($_POST['rds_aie_test_agent_nonce']) && wp_verify_nonce($_POST['rds_aie_test_agent_nonce'], 'rds_aie_test_agent')) {
	$agent_id = intval($_POST['agent_id']);
	$message = sanitize_textarea_field($_POST['message']);
	
	if ($agent_id && $message) {
		try {
			$result = rds_aie_agent($agent_id, $message, 'test_session_' . time());
			if (is_wp_error($result)) {
				$result = '<span style="color:red">Error: ' . $result->get_error_message() . '</span>';
			}
		} catch (Exception $e) {
			$result = '<span style="color:red">Exception: ' . $e->getMessage() . '</span>';
		}
	}
}
?>

<div class="wrap">
	<h2>Test Agent</h2>
	<form method="post">
		<?php wp_nonce_field('rds_aie_test_agent', 'rds_aie_test_agent_nonce'); ?>
		<table class="form-table">
			<tr>
				<th><label for="agent_id">Select Agent</label></th>
				<td>
					<select name="agent_id" id="agent_id">
						<option value="">-- Select --</option>
						<?php foreach ($agents as $agent): ?>
							<option value="<?php echo $agent->id; ?>"><?php echo esc_html($agent->name); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="message">Message</label></th>
				<td>
					<textarea name="message" id="message" rows="4" class="large-text"></textarea>
				</td>
			</tr>
			<tr>
				<th></th>
				<td><button type="submit" class="button button-primary">Run Agent</button></td>
			</tr>
		</table>
	</form>

	<?php if ($result): ?>
		<div class="notice notice-success inline">
			<h3>Result:</h3>
			<p><?php echo nl2br(esc_html($result)); ?></p>
		</div>
	<?php endif; ?>
</div>