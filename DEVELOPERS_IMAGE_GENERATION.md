# Руководство для разработчиков по интеграции ИИ с использованием RDS AI Engine

## 🔥 НОВОЕ: Генерация изображений

RDS AI Engine теперь поддерживает генерацию изображений через различные AI модели.

## Основные функции для генерации изображений

## 1. Базовая генерация изображений

### Через основной API:

```php
// Получаем экземпляр AI Engine
$ai_engine = RDS_AIE_Main::get_instance();

// Генерация изображения
$images = $ai_engine->image_generation([
    'model_id' => 1, // ID модели для генерации изображений
    'prompt' => 'A beautiful sunset over mountains',
    'session_id' => 'user_' . get_current_user_id(),
    'plugin_id' => 'my_plugin'
]);
```

### Через helper-функцию:

```php
$images = rds_aie_generate_image(
    'A cute cartoon cat wearing a hat',
    [
        'model_id' => 1,
        'override_params' => [
            'width' => '1024',
            'height' => '1024',
            'n' => 1,
            'quality' => 'standard',
            'seed' => 42,
            'response_format' => 'b64_json'
        ],
        'session_id' => 'user_' . get_current_user_id(),
        'plugin_id' => 'my_plugin'
    ]);
```

---

## 2. Универсальный метод генерации

Для работы как с текстом, так и с изображениями:

```php
// Генерация изображения
$result = rds_aie_generate(
    'A futuristic city skyline at night',
    [
        'model_id' => 1,
        'width' => '1024',
        'heght' => '1024'
    ]
);

// Генерация текста (обратная совместимость)
$text_result = rds_aie_generate(
    'Explain quantum computing',
    [
        'type' => 'text',
        'assistant_id' => 1
    ]
);
```

---

## 3. Параметры для генерации изображений

### Основные параметры:

```php
$params = [
    'model_id'       => 1,      // ID модели (обязательно для image)
    'prompt'         => '',     // Описание изображения (обязательно)
    'width'          => 1024,   // Ширина изображения (в пикселях)
    'height'         => 1024,   // Высота изображения (в пикселях)
    'quality'        => 'standard', // Качество: standard, hd
    'style'          => 'vivid', // Стиль: vivid, natural
    'n'              => 1,       // Количество изображений (1-4 для DALL-E)
    'response_format' => 'b64_json', // Формат ответа: url, b64_json
    'session_id'     => '',      // Идентификатор сессии
    'plugin_id'      => ''       // Идентификатор плагина
];
```

### Автоматическое определение параметров:

На основании, указанных значений высоты и ширины система автоматически генерирует:

- **для OpenRouter моделей** допустимое значение соотношения сторон
- **для других моделей** значение параметра `image_size` в формате `1024x1024`

---

## 4. Получение доступных image-моделей

```php
// Получаем все модели для генерации изображений
$image_models = rds_aie_get_models_by_type('image');

// Или модели, поддерживающие и текст, и изображения
$both_models = rds_aie_get_models_by_type('both');

// Получаем модель по умолчанию для изображений
$default_image_model = rds_aie_get_default_model_by_type('image');
```

---

## 5. Примеры использования

### Пример 1: Генерация миниатюр для постов

```php
class Post_Thumbnail_Generator {

    private $ai_engine;
    private $plugin_id = 'post_thumbnails';

    public function __construct() {
        $this->ai_engine = RDS_AIE_Main::get_instance();
        add_action('save_post', [$this, 'maybe_generate_thumbnail'], 10, 3);
    }

    public function maybe_generate_thumbnail($post_id, $post, $update) {
        // Проверяем условия для генерации
        if (!$update ||
            !$this->should_generate_thumbnail($post) ||
            has_post_thumbnail($post_id)) {
            return;
        }

        // Генерируем промпт на основе содержания поста
        $prompt = $this->create_prompt_from_post($post);

        try {
            // Генерируем изображение
            $images = $this->ai_engine->image_generation([
                'model_id' => $this->get_image_model_id(),
                'prompt' => $prompt,
                'session_id' => 'post_' . $post_id,
                'plugin_id' => $this->plugin_id,
                'override_params' => [
                    'width' => 1024,
                    'height' => 1024,
                    'quality' => 'standard'
                ]
            ]);

            if (!empty($images[0])) {
                $this->save_image_as_thumbnail($post_id, $images[0]);
            }

        } catch (Exception $e) {
            error_log('Thumbnail generation failed: ' . $e->getMessage());
        }
    }

    private function create_prompt_from_post($post) {
        $content = strip_tags($post->post_content);
        $excerpt = $post->post_excerpt ?: wp_trim_words($content, 30);

        return "Create a blog post thumbnail image for: " . $excerpt;
    }

    private function save_image_as_thumbnail($post_id, $base64_image) {
        // Конвертируем base64 в файл
        $upload_dir = wp_upload_dir();
        $filename = 'ai-thumbnail-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;

        // Извлекаем base64 данные
        if (preg_match('/data:image\/(\w+);base64,/', $base64_image, $matches)) {
            $image_data = substr($base64_image, strpos($base64_image, ',') + 1);
            $image_data = base64_decode($image_data);

            file_put_contents($filepath, $image_data);

            // Создаем attachment
            $attachment = [
                'post_mime_type' => 'image/' . $matches[1],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            ];

            $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);

            // Генерируем метаданные
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Устанавливаем как featured image
            set_post_thumbnail($post_id, $attach_id);
        }
    }
}
```

### Пример 2: Генерация изображений для продуктов WooCommerce

```php
class WooCommerce_AI_Images {

    public function __construct() {
        add_action('woocommerce_new_product', [$this, 'generate_product_images']);
        add_action('woocommerce_update_product', [$this, 'generate_product_images']);
        add_action('wp_ajax_generate_product_image', [$this, 'ajax_generate_image']);
    }

    public function generate_product_images($product_id) {
        $product = wc_get_product($product_id);

        if (!$product || $product->get_image_id()) {
            return;
        }

        $prompt = $this->build_product_prompt($product);

        try {
            $ai = RDS_AIE_Main::get_instance();

            $images = $ai->image_generation([
                'model_id' => $this->get_model_for_products(),
                'prompt' => $prompt,
                'session_id' => 'product_' . $product_id,
                'plugin_id' => 'woocommerce_ai',
                'override_params' => [
                    'width' => 1024,
                    'height' => 1024,
                    'quality' => 'hd',
                    'style' => 'vivid'
                ]
            ]);

            if (!empty($images)) {
                $this->save_product_images($product_id, $images);
            }

        } catch (Exception $e) {
            error_log('Product image generation failed: ' . $e->getMessage());
        }
    }

    private function build_product_prompt($product) {
        $prompt = "Professional product photo of " . $product->get_name();

        if ($description = $product->get_description()) {
            $prompt .= ". " . wp_trim_words(strip_tags($description), 20);
        }

        $prompt .= ". Clean white background, studio lighting, high quality, detailed";

        // Добавляем категории
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        if ($categories) {
            $prompt .= ", " . implode(', ', wp_list_pluck($categories, 'name'));
        }

        return $prompt;
    }
}
```

### Пример 3: AI Галерея изображений

```php
class AI_Image_Gallery {

    public function __construct() {
        add_shortcode('ai_gallery', [$this, 'render_gallery']);
        add_action('wp_ajax_ai_generate_gallery', [$this, 'ajax_generate_gallery']);
    }

    public function render_gallery($atts) {
        $atts = shortcode_atts([
            'theme' => 'nature',
            'count' => 4,
            'width' => 512,
            'height' => 512,
            'style' => 'digital art'
        ], $atts);

        // Проверяем кеш
        $cache_key = 'ai_gallery_' . md5(serialize($atts));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $this->render_gallery_html($cached);
        }

        // Генерируем новые изображения
        $images = $this->generate_gallery_images($atts);

        if (empty($images)) {
            return '<p>Failed to generate images. Please try again.</p>';
        }

        // Сохраняем в кеш
        set_transient($cache_key, $images, DAY_IN_SECONDS);

        return $this->render_gallery_html($images);
    }

    private function generate_gallery_images($atts) {
        $ai = RDS_AIE_Main::get_instance();
        $images = [];

        for ($i = 0; $i < $atts['count']; $i++) {
            $prompt = $this->generate_prompt($atts['theme'], $atts['style'], $i);

            try {
                $result = $ai->image_generation([
                    'model_id' => $this->get_gallery_model(),
                    'prompt' => $prompt,
                    'session_id' => 'gallery_' . $atts['theme'] . '_' . $i,
                    'plugin_id' => 'ai_gallery',
                    'override_params' => [
                        'width' => $atts['width'],
                        'height' => $atts['height'],
                        'quality' => 'standard'
                    ]
                ]);

                if (!empty($result[0])) {
                    $images[] = $result[0];
                }

            } catch (Exception $e) {
                error_log('Gallery image generation failed: ' . $e->getMessage());
            }

            // Пауза между запросами
            usleep(500000); // 0.5 секунды
        }

        return $images;
    }

    private function generate_prompt($theme, $style, $index) {
        $variations = [
            "Beautiful $theme landscape, $style style, vibrant colors",
            "Abstract interpretation of $theme, $style, creative composition",
            "Close-up detail of $theme, $style, intricate patterns",
            "Minimalist $theme concept, $style, clean lines"
        ];

        return $variations[$index % count($variations)];
    }

    private function render_gallery_html($images) {
        $html = '<div class="ai-gallery-grid">';

        foreach ($images as $image) {
            $html .= sprintf(
                '<div class="gallery-item"><img src="%s" alt="AI Generated Image"></div>',
                esc_url($image)
            );
        }

        $html .= '</div>';

        return $html;
    }
}
```

---

## 6. JavaScript интеграция для генерации изображений

```javascript
// ai-image-generator.js
class AIImageGenerator {
  constructor(options = {}) {
    this.options = {
      ajaxUrl: window.ajaxurl,
      nonce: "",
      defaultModelId: 0,
      ...options,
    };

    this.previewContainer = null;
    this.isGenerating = false;
  }

  async generate(prompt, params = {}) {
    if (this.isGenerating) {
      throw new Error("Another generation is in progress");
    }

    this.isGenerating = true;

    try {
      const response = await fetch(this.options.ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "generate_ai_image",
          nonce: this.options.nonce,
          prompt: prompt,
          model_id: params.model_id || this.options.defaultModelId,
          size: params.size || "1024x1024",
          aspect_ratio: params.aspect_ratio || "1:1",
          quality: params.quality || "standard",
          style: params.style || "vivid",
          session_id: params.session_id || "web_" + Date.now(),
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Generation failed");
      }

      return result.data.images;
    } catch (error) {
      console.error("Image generation failed:", error);
      throw error;
    } finally {
      this.isGenerating = false;
    }
  }

  showPreview(images, containerId = "ai-preview-container") {
    let container = document.getElementById(containerId);

    if (!container) {
      container = document.createElement("div");
      container.id = containerId;
      container.className = "ai-image-preview";
      document.body.appendChild(container);
    }

    container.innerHTML = "";

    images.forEach((imageData, index) => {
      const img = document.createElement("img");
      img.src = imageData;
      img.alt = `Generated image ${index + 1}`;
      img.className = "ai-generated-image";

      const wrapper = document.createElement("div");
      wrapper.className = "image-wrapper";
      wrapper.appendChild(img);

      // Добавляем кнопки действий
      const actions = this.createImageActions(imageData, index);
      wrapper.appendChild(actions);

      container.appendChild(wrapper);
    });

    return container;
  }

  createImageActions(imageData, index) {
    const actions = document.createElement("div");
    actions.className = "image-actions";

    const downloadBtn = document.createElement("button");
    downloadBtn.textContent = "Download";
    downloadBtn.onclick = () =>
      this.downloadImage(imageData, `ai-image-${index + 1}.png`);
    actions.appendChild(downloadBtn);

    const copyBtn = document.createElement("button");
    copyBtn.textContent = "Copy URL";
    copyBtn.onclick = () => this.copyToClipboard(imageData);
    actions.appendChild(copyBtn);

    return actions;
  }

  downloadImage(imageData, filename) {
    const link = document.createElement("a");
    link.href = imageData;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      alert("Image URL copied to clipboard!");
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  }

  // Stream generation (для больших изображений)
  async *streamGenerate(prompt, params = {}) {
    const response = await fetch(this.options.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "stream_generate_image",
        nonce: this.options.nonce,
        prompt: prompt,
        ...params,
      }),
    });

    if (!response.ok) {
      throw new Error(`Stream request failed: ${response.status}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    while (true) {
      const { done, value } = await reader.read();

      if (done) {
        break;
      }

      const chunk = decoder.decode(value);
      const lines = chunk.split("\n");

      for (const line of lines) {
        if (line.startsWith("data: ")) {
          const data = line.substring(6);

          if (data === "[DONE]") {
            return;
          }

          try {
            const parsed = JSON.parse(data);
            yield parsed;
          } catch (e) {
            console.warn("Failed to parse stream data:", e);
          }
        }
      }
    }
  }
}

// Пример использования с React/Vue
class ImageGeneratorUI {
  constructor() {
    this.generator = new AIImageGenerator({
      nonce: window.aiNonce,
      defaultModelId: window.defaultImageModelId,
    });

    this.initUI();
  }

  initUI() {
    // Создаем UI элементы
    this.createForm();
    this.bindEvents();
  }

  createForm() {
    this.form = document.createElement("div");
    this.form.className = "ai-image-generator-form";

    this.form.innerHTML = `
      <div class="form-group">
        <label for="prompt">Describe your image:</label>
        <textarea id="prompt" rows="4" placeholder="A beautiful sunset over mountains..."></textarea>
      </div>
      
      <div class="form-group">
        <label for="size">Size:</label>
        <select id="size">
          <option value="256x256">256x256</option>
          <option value="512x512">512x512</option>
          <option value="1024x1024" selected>1024x1024</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="aspect-ratio">Aspect Ratio:</label>
        <select id="aspect-ratio">
          <option value="1:1">Square (1:1)</option>
          <option value="4:3">Standard (4:3)</option>
          <option value="16:9">Widescreen (16:9)</option>
          <option value="9:16">Vertical (9:16)</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="style">Style:</label>
        <select id="style">
          <option value="vivid">Vivid</option>
          <option value="natural">Natural</option>
        </select>
      </div>
      
      <button id="generate-btn" class="generate-button">
        Generate Image
      </button>
      
      <div id="preview-container" class="preview-container"></div>
      <div id="status" class="status-message"></div>
    `;

    document.body.appendChild(this.form);
  }

  bindEvents() {
    document
      .getElementById("generate-btn")
      .addEventListener("click", async () => {
        await this.generateImage();
      });
  }

  async generateImage() {
    const prompt = document.getElementById("prompt").value.trim();

    if (!prompt) {
      this.showStatus("Please enter a description", "error");
      return;
    }

    this.showStatus("Generating image...", "info");
    this.disableForm(true);

    try {
      const images = await this.generator.generate(prompt, {
        size: document.getElementById("size").value,
        aspect_ratio: document.getElementById("aspect-ratio").value,
        style: document.getElementById("style").value,
      });

      this.generator.showPreview(images, "preview-container");
      this.showStatus("Image generated successfully!", "success");
    } catch (error) {
      this.showStatus(`Error: ${error.message}`, "error");
    } finally {
      this.disableForm(false);
    }
  }

  showStatus(message, type = "info") {
    const statusEl = document.getElementById("status");
    statusEl.textContent = message;
    statusEl.className = `status-message status-${type}`;
  }

  disableForm(disabled) {
    const elements = this.form.querySelectorAll("textarea, select, button");
    elements.forEach((el) => {
      el.disabled = disabled;
    });

    const btn = document.getElementById("generate-btn");
    btn.textContent = disabled ? "Generating..." : "Generate Image";
  }
}
```

---

## 7. Интеграция с WordPress Media Library

```php
class AI_Media_Library_Integration {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_scripts']);
        add_filter('media_upload_tabs', [$this, 'add_ai_generate_tab']);
        add_action('media_upload_ai_generate', [$this, 'render_ai_generate_interface']);
        add_action('wp_ajax_save_ai_image_to_library', [$this, 'save_to_media_library']);
    }

    public function enqueue_media_scripts($hook) {
        if ($hook !== 'media-upload-popup') {
            return;
        }

        wp_enqueue_script(
            'ai-media-library',
            plugin_dir_url(__FILE__) . 'js/ai-media-library.js',
            ['jquery', 'media-views'],
            '1.0.0',
            true
        );

        wp_localize_script('ai-media-library', 'aiMediaLibrary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_media_nonce'),
            'models' => rds_aie_get_models_by_type('image')
        ]);
    }

    public function add_ai_generate_tab($tabs) {
        $tabs['ai_generate'] = 'AI Generate';
        return $tabs;
    }

    public function render_ai_generate_interface() {
        // Проверяем права
        if (!current_user_can('upload_files')) {
            wp_die('You do not have permission to upload files.');
        }

        ?>
        <div class="wrap ai-generate-interface">
            <h1>Generate AI Images</h1>

            <div class="ai-generator-form">
                <div class="form-group">
                    <label for="ai-prompt">Image Description</label>
                    <textarea id="ai-prompt" rows="4" placeholder="Describe the image you want to generate..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-column">
                        <label for="ai-model">AI Model</label>
                        <select id="ai-model">
                            <?php
                            $models = rds_aie_get_models_by_type('image');
                            foreach ($models as $model): ?>
                                <option value="<?php echo esc_attr($model->id); ?>">
                                    <?php echo esc_html($model->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-column">
                        <label for="ai-width">Width</label>
                        <input type="number" id="ai-width" value="1024" min="256" max="2048">
                    </div>

                    <div class="form-column">
                        <label for="ai-height">Height</label>
                        <input type="number" id="ai-height" value="1024" min="256" max="2048">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-column">
                        <label for="ai-style">Style</label>
                        <select id="ai-style">
                            <option value="vivid">Vivid</option>
                            <option value="natural">Natural</option>
                        </select>
                    </div>

                    <div class="form-column">
                        <label for="ai-quality">Quality</label>
                        <select id="ai-quality">
                            <option value="standard">Standard</option>
                            <option value="hd">HD</option>
                        </select>
                    </div>
                </div>

                <button id="generate-button" class="button button-primary">
                    Generate Image
                </button>

                <div id="generation-status" class="status-message"></div>
            </div>

            <div id="image-preview" class="image-preview"></div>

            <div class="media-library-actions" style="display: none;">
                <button id="save-to-library" class="button button-secondary">
                    Save to Media Library
                </button>
                <button id="use-image" class="button button-primary">
                    Use This Image
                </button>
            </div>
        </div>

        <style>
            .ai-generate-interface {
                padding: 20px;
                max-width: 800px;
                margin: 0 auto;
            }
            .ai-generator-form {
                background: #fff;
                padding: 20px;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-row {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
            }
            .form-column {
                flex: 1;
            }
            textarea, select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .image-preview {
                margin-top: 20px;
                text-align: center;
            }
            .generated-image {
                max-width: 100%;
                height: auto;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .status-message {
                margin-top: 10px;
                padding: 10px;
                border-radius: 4px;
            }
            .status-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .status-error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            let currentImageData = null;

            $('#generate-button').on('click', function() {
                const prompt = $('#ai-prompt').val().trim();

                if (!prompt) {
                    showStatus('Please enter a description', 'error');
                    return;
                }

                $(this).prop('disabled', true).text('Generating...');
                showStatus('Generating image...', 'info');

                $.ajax({
                    url: aiMediaLibrary.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'generate_ai_image',
                        nonce: aiMediaLibrary.nonce,
                        prompt: prompt,
                        model_id: $('#ai-model').val(),
                        width: $('#ai-width').val(),
                        height: $('#ai-height').val(),
                        style: $('#ai-style').val(),
                        quality: $('#ai-quality').val()
                    },
                    success: function(response) {
                        if (response.success && response.data.images && response.data.images[0]) {
                            currentImageData = response.data.images[0];
                            showImagePreview(currentImageData);
                            $('.media-library-actions').show();
                            showStatus('Image generated successfully!', 'success');
                        } else {
                            showStatus('Failed to generate image: ' + (response.data?.message || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        showStatus('AJAX request failed', 'error');
                    },
                    complete: function() {
                        $('#generate-button').prop('disabled', false).text('Generate Image');
                    }
                });
            });

            $('#save-to-library').on('click', function() {
                if (!currentImageData) {
                    return;
                }

                $(this).prop('disabled', true).text('Saving...');

                $.ajax({
                    url: aiMediaLibrary.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'save_ai_image_to_library',
                        nonce: aiMediaLibrary.nonce,
                        image_data: currentImageData,
                        title: $('#ai-prompt').val().substring(0, 100)
                    },
                    success: function(response) {
                        if (response.success) {
                            showStatus('Image saved to media library!', 'success');

                            // Обновляем медиабиблиотеку
                            if (window.parent && window.parent.wp && window.parent.wp.media) {
                                const frame = window.parent.wp.media.frame;
                                if (frame) {
                                    frame.setState('library');
                                    frame.content.mode('browse');
                                }
                            }
                        } else {
                            showStatus('Failed to save image: ' + (response.data?.message || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        showStatus('Failed to save image', 'error');
                    },
                    complete: function() {
                        $('#save-to-library').prop('disabled', false).text('Save to Media Library');
                    }
                });
            });

            function showImagePreview(imageData) {
                const preview = $('#image-preview');
                preview.html(`<img src="${imageData}" class="generated-image" alt="Generated Image">`);
            }

            function showStatus(message, type) {
                const status = $('#generation-status');
                status.text(message).removeClass('status-success status-error status-info')
                                   .addClass('status-' + type);
            }
        });
        </script>
        <?php
    }

    public function save_to_media_library() {
        check_ajax_referer('ai_media_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $image_data = $_POST['image_data'];
        $title = sanitize_text_field($_POST['title']);

        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data provided']);
        }

        // Извлекаем base64 данные
        if (!preg_match('/data:image\/(\w+);base64,/', $image_data, $matches)) {
            wp_send_json_error(['message' => 'Invalid image format']);
        }

        $image_type = $matches[1];
        $image_data = substr($image_data, strpos($image_data, ',') + 1);
        $image_data = base64_decode($image_data);

        if (!$image_data) {
            wp_send_json_error(['message' => 'Failed to decode image data']);
        }

        $upload_dir = wp_upload_dir();
        $filename = 'ai-generated-' . time() . '.' . $image_type;
        $filepath = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($filepath, $image_data) === false) {
            wp_send_json_error(['message' => 'Failed to save image file']);
        }

        // Создаем attachment
        $attachment = [
            'post_mime_type' => 'image/' . $image_type,
            'post_title' => $title,
            'post_content' => 'AI Generated Image',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $filepath);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => $attach_id->get_error_message()]);
        }

        // Генерируем метаданные
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success([
            'attachment_id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id)
        ]);
    }
}
```

---

## 8. Best Practices для генерации изображений

### 1. Кеширование результатов

```php
class AI_Image_Cache {

    public function get_cached_image($prompt, $params, $generator_callback) {
        $cache_key = $this->generate_cache_key($prompt, $params);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $image = call_user_func($generator_callback, $prompt, $params);

        if ($image && !is_wp_error($image)) {
            set_transient($cache_key, $image, DAY_IN_SECONDS);
        }

        return $image;
    }

    private function generate_cache_key($prompt, $params) {
        $data = [
            'prompt' => $prompt,
            'params' => $params,
            'model_id' => $params['model_id'] ?? 0
        ];

        return 'ai_image_' . md5(serialize($data));
    }
}
```

### 2. Обработка ошибок и retry логика

```php
class AI_Image_Retry_Handler {

    public function generate_with_retry($prompt, $params, $max_retries = 3) {
        $retry_count = 0;

        while ($retry_count < $max_retries) {
            try {
                $ai = RDS_AIE_Main::get_instance();
                return $ai->image_generation(array_merge($params, ['prompt' => $prompt]));

            } catch (Exception $e) {
                $retry_count++;

                if ($retry_count >= $max_retries) {
                    throw $e;
                }

                // Экспоненциальная backoff задержка
                $delay = pow(2, $retry_count) * 1000000; // микросекунды
                usleep($delay);

                error_log(sprintf(
                    'Image generation failed, retry %d/%d: %s',
                    $retry_count,
                    $max_retries,
                    $e->getMessage()
                ));
            }
        }
    }
}
```

### 3. Валидация промптов

```php
class AI_Prompt_Validator {

    public static function validate_prompt($prompt, $context = 'image_generation') {
        // Проверка длины
        if (strlen($prompt) < 3) {
            throw new Exception('Prompt is too short');
        }

        if (strlen($prompt) > 4000) {
            throw new Exception('Prompt is too long (max 4000 characters)');
        }

        // Проверка на опасный контент
        $dangerous_patterns = [
            '/\b(child porn|cp|csam)\b/i',
            '/\b(extremist|terrorist|violence)\b/i',
            '/\b(hate speech|racist|sexist)\b/i',
            '/\b(self harm|suicide)\b/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $prompt)) {
                throw new Exception('Prompt contains prohibited content');
            }
        }

        // Проверка на спам/повторы
        if (preg_match('/(.)\1{10,}/', $prompt)) {
            throw new Exception('Prompt appears to be spam');
        }

        return true;
    }
}
```

### 4. Мониторинг использования

```php
class AI_Image_Usage_Tracker {

    public static function track_generation($user_id, $model_id, $prompt_length, $success) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_image_usage';

        $wpdb->insert($table_name, [
            'user_id' => $user_id,
            'model_id' => $model_id,
            'prompt_length' => $prompt_length,
            'success' => $success ? 1 : 0,
            'created_at' => current_time('mysql')
        ]);

        // Обновляем дневной лимит
        $daily_key = 'ai_image_daily_' . $user_id . '_' . date('Y-m-d');
        $daily_count = get_transient($daily_key) ?: 0;
        set_transient($daily_key, $daily_count + 1, DAY_IN_SECONDS);

        // Проверяем лимит
        $max_daily = apply_filters('ai_max_daily_images', 100);
        if ($daily_count >= $max_daily) {
            throw new Exception('Daily image generation limit reached');
        }
    }
}
```

## Заключение

**RDS AI Engine** предоставляет мощный и гибкий API для генерации изображений, который позволяет:

1. **Интегрировать AI-генерацию** в любой WordPress плагин
2. **Поддерживать multiple провайдеров** (OpenRouter, OpenAI, Google и др.)
3. **Автоматически определять** доступные параметры для каждой модели
4. **Обеспечивать безопасность** и контроль использования
5. **Интегрироваться с Media Library** WordPress
6. **Предоставлять rich JavaScript API** для frontend интеграции

Используя представленные шаблоны и лучшие практики, вы можете создавать сложные AI-приложения для генерации изображений, от простых миниатюр до сложных галерей и медиабиблиотек.

## 9. Сохранение и управление историей генерации изображений

При использовании RDS AI Engine для генерации изображений, все результаты генерации автоматически сохраняются в базе данных WordPress. Это позволяет отслеживать историю генерации, повторно использовать изображения и анализировать использование.

### 9.1. Структура таблицы в базе данных

При генерации изображений данные сохраняются в таблице `{prefix}_rds_aie_generations` со следующими полями:

- `id` (int) - Уникальный идентификатор записи
- `model_id` (int) - ID модели, использованной для генерации
- `session_id` (varchar) - Идентификатор сессии, связывающий генерации
- `plugin_id` (varchar) - Идентификатор вашего плагина (значение по умолчанию: 'default')
- `user_id` (int) - ID пользователя, инициировавшего генерацию
- `type` (enum) - Тип генерации ('text', 'image'), для изображений всегда 'image'
- `prompt` (text) - Текст промпта, использованного для генерации
- `parameters` (text) - JSON-представление всех параметров генерации
- `response_data` (longtext) - JSON-ответ от AI сервиса (обычно содержит base64 изображения)
- `response_format` (varchar) - Формат ответа ('b64_json', 'url', и т.д.)
- `tokens_used` (int) - Количество использованных токенов (если поддерживается моделью)
- `status` (enum) - Статус генерации ('pending', 'success', 'error')
- `error_message` (text) - Сообщение об ошибке, если генерация завершилась неудачей
- `created_at` (datetime) - Дата и время создания записи

### 9.2. Публичные методы для работы с историей генерации

RDS AI Engine предоставляет следующие публичные методы для получения данных о генерации:

#### Получение конкретной генерации по ID:

```php
// Получение экземпляра DB
$db = new RDS_AIE_DB();

// Получение генерации по ID
$generation = $db->get_generation($id);

if ($generation) {
    echo "Prompt: " . $generation->prompt;
    echo "Response Format: " . $generation->response_format;
    print_r($generation->parameters); // Все параметры генерации
    print_r($generation->response_data); // Ответ от AI сервиса
}
```

#### Получение генераций по сессии:

```php
// Получение всех генераций для конкретной сессии
$generations = $db->get_generations_by_session($session_id, $limit = 20);

foreach ($generations as $gen) {
    echo "ID: " . $gen->id;
    echo "Prompt: " . $gen->prompt;
    echo "Created: " . $gen->created_at;
    // ...
}
```

#### Сохранение результата генерации:

```php
// При использовании основного API генерации, сохранение происходит автоматически
$images = rds_aie_generate_image(
    'A cute cartoon cat wearing a hat',
    [
        'model_id' => 1,
        'session_id' => 'user_123',
        'plugin_id' => 'my_plugin',
        'type' => 'image'
    ]
);

// Но вы можете также вручную сохранить данные:
$db = new RDS_AIE_DB();
$db->save_generation([
    'model_id' => 1,
    'session_id' => 'my_session',
    'plugin_id' => 'my_plugin',
    'user_id' => get_current_user_id(),
    'type' => 'image',
    'prompt' => 'My image prompt',
    'parameters' => ['width' => 1024, 'height' => 1024, 'quality' => 'standard'],
    'response_data' => ['images' => [...]], // Ответ от AI
    'status' => 'success'
]);
```

### 9.3. Автоматическая очистка старых записей

RDS AI Engine включает в себя функциональность автоматической очистки старых записей генерации:

- По умолчанию, записи о генерации изображений хранятся 1 час, после чего автоматически удаляются
- Администратор может изменить период хранения в административной панели плагина
- Также доступна ручная очистка через интерфейс администратора

Для программной очистки старых записей используйте:

```php
// Очистка генераций старше указанного количества часов
$db = new RDS_AIE_DB();
$deleted_count = $db->cleanup_old_generations($hours = 1); // Удалить генерации старше 1 часа
echo "Удалено $deleted_count записей";
```

### 9.4. Рекомендации по работе с историей

1. Всегда указывайте уникальный `session_id` для группировки связанных генераций
2. Используйте осмысленный `plugin_id` для идентификации источника генерации
3. При повторном использовании изображений проверяйте наличие в истории перед новой генерацией
4. Для экономии места в базе данных, своевременно очищайте устаревшие записи
5. Обрабатывайте статус `error` при получении генераций, чтобы выявлять проблемы с API
