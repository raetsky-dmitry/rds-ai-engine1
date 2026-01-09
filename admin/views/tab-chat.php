<?php

/**
 * Вкладка тестового чата
 */

$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();
$assistant_manager = $main->get_assistant_manager();

$models = $model_manager->get_all();
$assistants = $assistant_manager->get_all();
?>

<div class="rds-aie-chat">
	<h2><?php _e('Test Chat', 'rds-ai-engine'); ?></h2>
	<p class="description"><?php _e('Test your AI models and assistants in real-time.', 'rds-ai-engine'); ?></p>

	<div class="chat-container">
		<div class="chat-controls">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="chat_model"><?php _e('AI Model', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="chat_model" class="regular-text">
							<option value=""><?php _e('-- Select Model --', 'rds-ai-engine'); ?></option>
							<?php foreach ($models as $model): ?>
								<option value="<?php echo esc_attr($model->id); ?>">
									<?php echo esc_html($model->name); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="chat_assistant"><?php _e('Assistant', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="chat_assistant" class="regular-text">
							<option value=""><?php _e('-- Select Assistant --', 'rds-ai-engine'); ?></option>
							<?php foreach ($assistants as $assistant): ?>
								<option value="<?php echo esc_attr($assistant->id); ?>">
									<?php echo esc_html($assistant->name); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div class="chat-messages" id="chatMessages">
			<div class="chat-welcome">
				<p><?php _e('Start a conversation by typing a message below.', 'rds-ai-engine'); ?></p>
			</div>
		</div>

		<!-- БЛОК ОТЛАДКИ (изначально скрыт) -->
		<div class="debug-container" id="debugContainer" style="display: none;">
			<h3><?php _e('Debug Information', 'rds-ai-engine'); ?></h3>
			<div class="debug-tabs">
				<button class="debug-tab active" data-tab="request"><?php _e('Request Info', 'rds-ai-engine'); ?></button>
				<button class="debug-tab" data-tab="history"><?php _e('Conversation History', 'rds-ai-engine'); ?></button>
				<button class="debug-tab" data-tab="fullrequest"><?php _e('Full AI Request', 'rds-ai-engine'); ?></button>
				<button class="debug-tab" data-tab="response"><?php _e('AI Response', 'rds-ai-engine'); ?></button>
			</div>
			<div class="debug-content">
				<div class="debug-tab-content active" id="debugRequest">
					<pre><code id="debugRequestContent"><?php _e('No request data yet...', 'rds-ai-engine'); ?></code></pre>
				</div>
				<div class="debug-tab-content" id="debugHistory">
					<pre><code id="debugHistoryContent"><?php _e('No history data yet...', 'rds-ai-engine'); ?></code></pre>
				</div>
				<div class="debug-tab-content" id="debugFullrequest">
					<pre><code id="debugFullrequestContent"><?php _e('No full request data yet...', 'rds-ai-engine'); ?></code></pre>
				</div>
				<div class="debug-tab-content" id="debugResponse">
					<pre><code id="debugResponseContent"><?php _e('No response data yet...', 'rds-ai-engine'); ?></code></pre>
				</div>
			</div>
		</div>

		<div class="chat-input">
			<textarea id="chatInput" placeholder="<?php esc_attr_e('Type your message here...', 'rds-ai-engine'); ?>"
				rows="3"></textarea>
			<div class="chat-actions">
				<button id="sendMessage" class="button button-primary" disabled>
					<?php _e('Send', 'rds-ai-engine'); ?>
				</button>
				<button id="clearChat" class="button">
					<?php _e('Clear Chat', 'rds-ai-engine'); ?>
				</button>
				<button id="toggleDebug" class="button button-secondary">
					<?php _e('Show Debug', 'rds-ai-engine'); ?>
				</button>
			</div>
		</div>
	</div>
</div>