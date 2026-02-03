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
if (isset($_POST['action']) && $_POST['action'] === 'test_image_generation') {
	if (wp_verify_nonce($_POST['_wpnonce'], 'rds_aie_test_image')) {
		$model_id = intval($_POST['model_id']);
		$prompt = sanitize_textarea_field($_POST['prompt']);
		$aspect_ratio = sanitize_text_field($_POST['aspect_ratio']);
		$size = sanitize_text_field($_POST['size'] ?? '1024x1024');
		
		// Генерируем случайный seed, если не передан
		$seed = intval($_POST['seed'] ?? $_POST['openrouter_seed'] ?? mt_rand(0, 99999));
		if ($seed <= 0) {
			$seed = mt_rand(0, 99999);
		}

		try {
			// Получаем информацию о модели для определения типа
			$model = $model_manager->get($model_id);
			$is_openrouter = false;
			if ($model && strpos($model->base_url, 'openrouter.ai') !== false) {
				$is_openrouter = true;
			}

			// Определяем базовые параметры
			$override_params = [
				'n' => 1,
				'response_format' => 'b64_json'
			];

			// Добавляем параметры в зависимости от типа модели
			if ($is_openrouter) {
				$override_params['aspect_ratio'] = $aspect_ratio;
				$override_params['seed'] = $seed;
			} else {
				$override_params['size'] = $size;
				$override_params['seed'] = $seed;
			}

			// Для некоторых моделей OpenRouter можно добавить image_size
			if ($model && strpos($model->model_name, 'gemini') !== false) {
				$override_params['image_size'] = 'standard';
			}

			$test_result = $main->image_generation([
				'model_id' => $model_id,
				'prompt' => $prompt,
				'override_params' => $override_params
			]);

			// Оборачиваем результат в нужный формат
			$test_result = [
				'success' => true,
				'message' => __('Image generated successfully.', 'rds-ai-engine'),
				'result' => $test_result
			];
		} catch (Exception $e) {
			$test_result = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
	}
}
?>

<div class="rds-aie-image-test">
	<h2><?php _e('Test Image Generation', 'rds-ai-engine'); ?></h2>
	<p class="description"><?php _e('Test your image generation models with different prompts.', 'rds-ai-engine'); ?></p>

	<?php if ($test_result): ?>
		<div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>">
			<p><strong><?php echo $test_result['success'] ? __('Success:', 'rds-ai-engine') : __('Error:', 'rds-ai-engine'); ?></strong>
				<?php echo esc_html($test_result['message']); ?></p>

			<?php if ($test_result['success'] && !empty($test_result['result'])): ?>
				<div class="test-images">
					<h3><?php _e('Generated Images:', 'rds-ai-engine'); ?></h3>
					<div class="image-grid">
						<?php foreach ($test_result['result'] as $index => $image_data): ?>
							<div class="image-item">
								<div class="image-container">
									<img src="<?php echo esc_attr($image_data); ?>"
										alt="<?php printf(__('Generated image %d', 'rds-ai-engine'), $index + 1); ?>"
										style="max-width: 100%; height: auto;">
								</div>
								<div class="image-actions">
									<button type="button" class="button button-small copy-base64"
										data-base64="<?php echo esc_attr($image_data); ?>">
										<?php _e('Copy Base64', 'rds-ai-engine'); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
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
				<tr class="conditional-fields" style="display: none;">
					<th scope="row">
						<label for="prompt"><?php _e('Prompt', 'rds-ai-engine'); ?> *</label>
					</th>
					<td>
						<textarea id="prompt" name="prompt" rows="4" class="large-text" required
							placeholder="<?php esc_attr_e('Describe the image you want to generate...', 'rds-ai-engine'); ?>"></textarea>
						<p class="description"><?php _e('Detailed description of the image you want to generate.', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr class="conditional-fields openrouter-fields" style="display: none;">
					<th scope="row">
						<label for="aspect_ratio"><?php _e('Aspect Ratio', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="aspect_ratio" name="aspect_ratio" class="regular-text">
							<option value="1:1">1:1 (Square)</option>
							<option value="4:3">4:3 (Standard)</option>
							<option value="3:4">3:4 (Portrait)</option>
							<option value="16:9">16:9 (Widescreen)</option>
							<option value="9:16">9:16 (Vertical)</option>
						</select>
						<p class="description"><?php _e('Only for OpenRouter models', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr class="conditional-fields openrouter-fields" style="display: none;">
					<th scope="row">
						<label for="openrouter_seed"><?php _e('Seed', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="openrouter_seed" name="openrouter_seed" min="0" max="99999" class="regular-text" value="" placeholder="<?php _e('Leave empty for random seed', 'rds-ai-engine'); ?>" />
						<p class="description"><?php _e('Random seed for image generation (for OpenRouter models)', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr class="conditional-fields non-openrouter-fields" style="display: none;">
					<th scope="row">
						<label for="size"><?php _e('Image Size', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<select id="size" name="size" class="regular-text">
							<option value="256x256">256x256</option>
							<option value="512x512">512x512</option>
							<option value="768x768">768x768</option>
							<option value="1024x1024" selected>1024x1024</option>
							<option value="1024x768">1024x768</option>
							<option value="1280x720">1280x720</option>
							<option value="1280x1024">1280x1024</option>
							<option value="1536x640">1536x640</option>
							<option value="1536x768">1536x768</option>
							<option value="1536x1024">1536x1024</option>
							<option value="1792x1024">1792x1024</option>
							<option value="1920x1080">1920x1080</option>
							<option value="2048x1024">2048x1024</option>
							<option value="1024x1792">1024x1792</option>
							<option value="1080x1920">1080x1920</option>
						</select>
						<p class="description"><?php _e('Size of the generated image (only for non-OpenRouter models)', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
				<tr class="conditional-fields non-openrouter-fields" style="display: none;">
					<th scope="row">
						<label for="seed"><?php _e('Seed', 'rds-ai-engine'); ?></label>
					</th>
					<td>
						<input type="number" id="seed" name="seed" min="0" max="99999" class="regular-text" value="" placeholder="<?php _e('Leave empty for random seed', 'rds-ai-engine'); ?>" />
						<p class="description"><?php _e('Random seed for image generation (for non-OpenRouter models)', 'rds-ai-engine'); ?></p>
					</td>
				</tr>
			</table>

			<!-- Оставляем скрытые поля для стандартных параметров -->
			<input type="hidden" id="n" name="n" value="1">
			<input type="hidden" id="quality" name="quality" value="standard">
			<input type="hidden" id="style" name="style" value="vivid">
			<input type="hidden" id="response_format" name="response_format" value="b64_json">
			<input type="hidden" id="image_size" name="image_size" value="standard">

			<p class="submit conditional-fields" style="display: none;">
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

	.test-images h3 {
		margin-top: 20px;
		margin-bottom: 10px;
	}

	.image-params .small-text {
		width: 120px;
		margin-left: 5px;
	}
	
	.openrouter-fields, .non-openrouter-fields {
		transition: opacity 0.3s ease;
	}
	
	.conditional-fields {
		opacity: 1;
		transition: opacity 0.3s ease;
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

<script>
	jQuery(document).ready(function($) {
		// Определяем, является ли выбранная модель OpenRouter
		function isOpenRouterModel(modelName) {
			if (!modelName) return false;
			return modelName.toLowerCase().includes('openrouter') ||
				modelName.toLowerCase().includes('openai/') ||
				modelName.toLowerCase().includes('google/') ||
				modelName.toLowerCase().includes('stability-ai/') ||
				modelName.toLowerCase().includes('black-forest-labs/');
		}

		// Показываем/скрываем поля в зависимости от модели
		$('#model_id').on('change', function() {
			var selectedValue = $(this).find('option:selected').text().toLowerCase();
			var selectedModelId = $(this).val(); // Проверяем, выбрана ли модель
			var isOpenRouter = isOpenRouterModel(selectedValue);

			// Если модель выбрана (не пустое значение), показываем дополнительные поля
			if (selectedModelId) {
				$('.conditional-fields').show();
				
				if (isOpenRouter) {
					// Показываем поля для OpenRouter
					$('.openrouter-fields').show();
					// Скрываем поля для других моделей
					$('.non-openrouter-fields').hide();
					
					// Автоматически генерируем seed для OpenRouter, если не заполнено
					if ($('#openrouter_seed').val() === '') {
						$('#openrouter_seed').val(Math.floor(Math.random() * 100000));
					}
				} else {
					// Скрываем поля для OpenRouter
					$('.openrouter-fields').hide();
					// Показываем поля для других моделей
					$('.non-openrouter-fields').show();
					
					// Автоматически генерируем seed для других моделей, если не заполнено
					if ($('#seed').val() === '') {
						$('#seed').val(Math.floor(Math.random() * 100000));
					}
				}
			} else {
				// Если модель не выбрана, скрываем все условные поля
				$('.conditional-fields').hide();
			}
		});

		// Инициализация при загрузке
		$('#model_id').trigger('change');
	});
</script>