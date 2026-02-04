<?php

/**
 * Вкладка тестирования генерации изображений
 */

$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();

$image_models = $model_manager->get_models_by_type('image');
$both_models = $model_manager->get_models_by_type('both');
$models = array_merge($image_models, $both_models);

// Обработка тестовой генерации
$test_result = null;
$error_message = null;
if (isset($_POST['action']) && $_POST['action'] === 'test_image_generation') {
	if (wp_verify_nonce($_POST['_wpnonce'], 'rds_aie_test_image')) {
		$model_id = intval($_POST['model_id']);
		$prompt = sanitize_textarea_field($_POST['prompt']);
		$width = sanitize_text_field($_POST['width'] ?? '1024');
		$height = sanitize_text_field($_POST['height'] ?? '1024');

		// Генерируем случайный seed, если не передан
		$seed = intval($_POST['seed'] ?? mt_rand(0, 99999));
		if ($seed <= 0) {
			$seed = mt_rand(0, 99999);
		}

		try {
			// Получаем информацию о модели для определения типа
			$model = $model_manager->get($model_id);

			// Определяем базовые параметры
			$override_params = [
				'n' => 1,
				'response_format' => 'b64_json'
			];

			// Добавляем параметры ширины и высоты
			$override_params['width'] = $width;
			$override_params['height'] = $height;
			$override_params['seed'] = $seed;

			// Генерируем изображение
			$test_result = $main->image_generation([
				'model_id' => $model_id,
				'prompt' => $prompt,
				'override_params' => $override_params
			]);
		} catch (Exception $e) {
			$error_message = $e->getMessage();
		}
	}
}
?>

<div class="wrap">
	<h1><?php _e('Test Image Generation', 'rds-ai-engine'); ?></h1>

	<?php if (isset($error_message)): ?>
		<div class="notice notice-error">
			<p><?php echo esc_html($error_message); ?></p>
		</div>
	<?php endif; ?>

	<?php if ($test_result && is_array($test_result) && !empty($test_result)): ?>
		<div class="notice notice-success">
			<p><?php _e('Image generated successfully!', 'rds-ai-engine'); ?></p>
		</div>
		<div class="image-results">
			<h3><?php _e('Generated Images', 'rds-ai-engine'); ?> (<?php echo count($test_result); ?>)</h3>
			<div class="image-grid">
				<?php foreach ($test_result as $index => $image_data): ?>
					<div class="image-item">
						<div class="image-container">
							<img src="<?php echo esc_attr($image_data); ?>" alt="<?php _e('Generated image', 'rds-ai-engine'); ?> <?php echo $index + 1; ?>" style="max-width: 100%; height: auto;">
						</div>
						<div class="image-actions">
							<button type="button" class="button button-small copy-base64" data-base64="<?php echo esc_attr($image_data); ?>">
								<?php _e('Copy Base64', 'rds-ai-engine'); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php elseif ($test_result && !is_array($test_result)): ?>
		<div class="notice notice-error">
			<p><?php _e('Invalid image data returned. Expected an array of images.', 'rds-ai-engine'); ?></p>
		</div>
	<?php endif; ?>

	<!-- Форма тестирования -->
	<div class="test-form">
		<form method="post">
			<?php wp_nonce_field('rds_aie_test_image'); ?>
			<input type="hidden" name="action" value="test_image_generation">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="model_id"><?php _e('Image Model', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<?php if (empty($models)): ?>
							<p class="description" style="color: #dc3232;">
								<?php _e('No image generation models configured. Please add a model with type "Image" or "Both".', 'rds-ai-engine'); ?>
							</p>
						<?php else: ?>
							<select id="model_id" name="model_id" class="regular-text" required>
								<option value=""><?php _e('-- Select Model --', 'rds-ai-engine'); ?></option>
								<?php foreach ($models as $model): ?>
									<option value="<?php echo esc_attr($model->id); ?>">
										<?php echo esc_html($model->name); ?> (<?php echo esc_html($model->model_name); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="prompt"><?php _e('Prompt', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<textarea id="prompt" name="prompt" rows="4" class="large-text" required
							placeholder="<?php esc_attr_e('Describe the image you want to generate...', 'rds-ai-engine'); ?>"></textarea>
						<p class="description"><?php _e('Detailed description of the image you want to generate.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="width"><?php _e('Width', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="width" name="width" class="regular-text">
							<option value="256">256px</option>
							<option value="512">512px</option>
							<option value="768">768px</option>
							<option value="1024" selected>1024px</option>
							<option value="1280">1280px</option>
							<option value="1536">1536px</option>
							<option value="1792">1792px</option>
							<option value="2048">2048px</option>
						</select>
						<p class="description"><?php _e('Width of the generated image', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="height"><?php _e('Height', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="height" name="height" class="regular-text">
							<option value="256">256px</option>
							<option value="512">512px</option>
							<option value="768">768px</option>
							<option value="1024" selected>1024px</option>
							<option value="1280">1280px</option>
							<option value="1536">1536px</option>
							<option value="1792">1792px</option>
							<option value="2048">2048px</option>
						</select>
						<p class="description"><?php _e('Height of the generated image', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="seed"><?php _e('Seed', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="seed" name="seed" min="0" max="99999" class="regular-text" value="" placeholder="<?php _e('Leave empty for random seed', 'rds-ai-engine'); ?>" />
						<p class="description"><?php _e('Random seed for image generation', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
			</table>

			<!-- Оставляем скрытые поля для стандартных параметров -->
			<input type="hidden" id="n" name="n" value="1">
			<input type="hidden" id="quality" name="quality" value="standard">
			<input type="hidden" id="style" name="style" value="vivid">
			<input type="hidden" id="response_format" name="response_format" value="b64_json">
			<input type="hidden" id="image_size" name="image_size" value="standard">

			<p class="submit">
				<button type="submit" class="button button-primary" <?php echo empty($models) ? 'disabled' : ''; ?>>
					<?php _e('Generate Image', 'rds-ai-engine'); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
	.image-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
		gap: 20px;
		margin: 20px 0;
	}

	.image-item {
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 10px;
		background: #fff;
	}

	.image-container {
		margin-bottom: 10px;
		text-align: center;
	}

	.image-actions {
		text-align: center;
	}

	.image-results h3 {
		margin-top: 20px;
		margin-bottom: 10px;
	}
</style>

<script>
	jQuery(document).ready(function($) {
		// Копирование base64 в буфер обмена
		$('.copy-base64').on('click', function() {
			var base64 = $(this).data('base64');
			var tempInput = $('<textarea>');
			$('body').append(tempInput);
			tempInput.val(base64).select();
			document.execCommand('copy');
			tempInput.remove();

			// Визуальная обратная связь
			var originalText = $(this).text();
			$(this).text('<?php _e('Copied!', 'rds-ai-engine'); ?>');
			setTimeout(function() {
				$(this).text(originalText);
			}.bind(this), 2000);
		});
	});
</script>