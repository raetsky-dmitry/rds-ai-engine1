# Руководство для разработчиков по интеграции ИИ с использованием RDS AI Engine

Этот документ предназначен **для разработчиков WordPress‑плагинов**, которые хотят интегрировать ИИ‑функциональность через **RDS AI Engine**.

## Обзор архитектуры

**RDS AI Engine** - это базовый плагин WordPress, который предоставляет централизованный интерфейс для работы с OpenAI-совместимыми API. Он выполняет роль промежуточного слоя между вашими плагинами и различными ИИ-сервисами.

### Ключевые возможности для разработчиков:

- централизует работу с OpenAI‑совместимыми API;
- управляет моделями, ассистентами и историей;
- обеспечивает безопасность и мультитенантность;
- избавляет плагины от необходимости напрямую работать с AI API.

## Установка и активация зависимостей

### Шаг 1: Установка RDS AI Engine

```bash
# Ваш плагин должен проверять наличие RDS AI Engine
add_action('admin_init', function() {
    if (!class_exists('RDS_AIE_Main')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error">
                <p>Для работы плагина требуется установить и активировать RDS AI Engine.</p>
            </div>';
        });
    }
});
```

### Шаг 2: Добавление зависимости в свой плагин

В главном файле плагина добавьте:

```php
/**
 * Plugin Name: My AI Plugin
 * Requires Plugins: rds-ai-engine
 * Requires PHP: 7.4
 */
```

## 1. Ключевая концепция

**Ваш плагин не работает с AI напрямую.**

Он:

- не знает API‑ключей;
- не формирует HTTP‑запросы к OpenAI;
- не хранит историю диалогов.

Всё это делает **RDS AI Engine**.

С точки зрения архитектуры:

```
[Ваш плагин]
     ↓
RDS_AIE_Main (Singleton)
     ↓
Model Manager / Assistant Manager / History Manager
     ↓
OpenAI‑совместимый API
```

---

## 2. Точка входа — `RDS_AIE_Main`

Всегда начинайте с получения экземпляра:

```php
$ai_engine = RDS_AIE_Main::get_instance();
```

Это singleton — один экземпляр на весь WordPress.

---

## 3. Ассистенты — базовая единица интеграции

### Что такое ассистент

Ассистент — это:

- system prompt (роль и поведение ИИ);
- настройки генерации (temperature, max_tokens);
- модель по умолчанию;
- правила работы с историей.

**Ассистенты — рекомендуемый способ интеграции.**

---

## 4. Создание ассистентов из других плагинов

### Основной метод: `get_or_create_assistant()`

Этот метод:

- ищет ассистента по имени;
- если найден — возвращает его ID;
- если нет — создаёт нового.

```php
$assistant_id = $ai_engine->get_or_create_assistant(
    'My Plugin Assistant',
    [
        'system_prompt' => 'Ты помогаешь пользователям работать с плагином My Plugin...',
        'temperature'   => 0.7,
        'max_tokens'    => 1000,
        'history_enabled' => true,
        'history_messages_count' => 10
    ]
);
```

✔️ Идемпотентно
✔️ Без дублей
✔️ Без зависимости от админки

---

### Когда вызывать создание ассистента

**Рекомендуемые варианты:**

1. `plugins_loaded`

```php
add_action('plugins_loaded', function () {
    RDS_AIE_Main::get_instance()->get_or_create_assistant(...);
});
```

2. `register_activation_hook`

3. Lazy‑инициализация (по первому AI‑запросу)

---

## 5. Регистрация ассистентов через фильтр (advanced)

RDS AI Engine поддерживает массовую регистрацию ассистентов:

```php
add_filter('rds_aie_default_assistants', function ($assistants) {
    $assistants[] = [
        'name' => 'My Plugin Support Bot',
        'system_prompt' => 'Ты саппорт‑бот плагина My Plugin...',
        'temperature' => 0.6,
        'max_tokens' => 1200
    ];

    return $assistants;
});
```

Ассистенты будут автоматически созданы при инициализации AI Engine.

---

## 6. Отправка сообщений в ИИ

### Основной метод: `chat_completion()`

```php
$response = $ai_engine->chat_completion([
    'assistant_id' => $assistant_id,
    'message'      => 'Как настроить плагин?',
    'session_id'   => 'user_' . get_current_user_id(),
    'plugin_id'    => 'my_plugin'
]);
```

### Через helper‑функцию

```php
$response = rds_aie_chat(
    'Как настроить плагин?',
    $assistant_id,
    [
        'session_id' => 'user_' . get_current_user_id(),
        'plugin_id'  => 'my_plugin'
    ]
);
```

---

## 7. Параметры `chat_completion`

| Параметр        | Описание                        |
| --------------- | ------------------------------- |
| message         | Сообщение пользователя          |
| assistant_id    | ID ассистента                   |
| model_id        | ID модели (если без ассистента) |
| session_id      | Идентификатор диалога           |
| plugin_id       | Идентификатор плагина           |
| override_params | Перезапись параметров модели    |

---

## 8. Сессии и история диалогов

### Зачем нужен `session_id`

- связывает сообщения в диалог;
- используется для истории;
- изолирует разные контексты.

### Рекомендуемый формат

```
{plugin}_{user}_{context}
```

Пример:

```php
support_15_ticket_102
```

---

### Получение истории

```php
$history = $ai_engine
    ->get_history_manager()
    ->get_formatted_history($session_id, 10);
```

### Очистка истории

```php
$ai_engine->get_history_manager()->clear_history($session_id);
```

---

## 9. Frontend‑интеграция (JavaScript)

RDS AI Engine **официально поддерживает frontend‑интеграцию через JS‑клиент**.

### Общая схема

```
JS‑класс → WordPress AJAX → RDS AI Engine → AI
```

API‑ключи никогда не попадают на клиент.

---

### JS‑клиент (пример)

```javascript
class MyPluginAIClient {
  constructor(assistantId, modelId = 0) {
    this.assistantId = assistantId;
    this.modelId = modelId;
    this.sessionId = this.generateSessionId();
  }

  sendMessage(message) {
    return jQuery.post(myPluginAjax.ajax_url, {
      action: "my_plugin_ai_chat",
      nonce: myPluginAjax.nonce,
      message,
      assistant_id: this.assistantId,
      model_id: this.modelId,
      session_id: this.sessionId,
    });
  }

  generateSessionId() {
    return "myplugin_" + Date.now();
  }
}
```

---

### PHP AJAX‑хендлер

```php
add_action('wp_ajax_my_plugin_ai_chat', 'my_plugin_ai_chat');
add_action('wp_ajax_nopriv_my_plugin_ai_chat', 'my_plugin_ai_chat');

function my_plugin_ai_chat() {
    check_ajax_referer('my_plugin_nonce', 'nonce');

    $ai = RDS_AIE_Main::get_instance();

    $response = $ai->chat_completion([
        'assistant_id' => intval($_POST['assistant_id']),
        'model_id'     => intval($_POST['model_id']),
        'message'      => sanitize_textarea_field($_POST['message']),
        'session_id'   => sanitize_text_field($_POST['session_id']),
        'plugin_id'    => 'my_plugin'
    ]);

    wp_send_json_success(['response' => $response]);
}
```

---

## 10. Мультитенантность (`plugin_id`)

Всегда указывайте `plugin_id`:

```php
'plugin_id' => 'my_plugin_slug'
```

Это обеспечивает:

- изоляцию истории;
- безопасное сосуществование плагинов;
- корректную аналитику.

---

## 11. Хуки и расширение логики

### Фильтры

```php
add_filter('rds_aie_chat_completion_params', function ($params) {
    $params['top_p'] = 0.9;
    return $params;
});
```

### Actions

```php
add_action('rds_aie_chat_completion_success', function ($response, $params) {
    // логирование, аналитика
});
```

---

## 12. Best Practices

✅ Используйте ассистентов
✅ Используйте session_id
✅ Используйте plugin_id
✅ Обрабатывайте ошибки

❌ Не передавайте API‑ключи на фронт
❌ Не работайте с AI API напрямую
❌ Не используйте один session_id для всех

---

## Базовые шаблоны интеграции

### Шаблон 1: Простая интеграция

```php
<?php
/**
 * Простой пример интеграции ИИ в плагин
 */

if (!defined('ABSPATH')) {
    exit;
}

class My_AI_Plugin {

    private $ai_engine;
    private $assistant_id;
    private $plugin_id = 'my_ai_plugin';

    public function __construct() {
        // Проверяем наличие AI Engine
        if (!class_exists('RDS_AIE_Main')) {
            add_action('admin_notices', [$this, 'show_missing_dependency_notice']);
            return;
        }

        // Инициализируем AI Engine
        $this->ai_engine = RDS_AIE_Main::get_instance();

        // ID нашего ассистента (можно получать из настроек)
        $this->assistant_id = $this->get_or_create_assistant();

        $this->init();
    }

    public function init() {
        // Регистрируем хуки и обработчики
        add_action('wp_ajax_my_plugin_ai_request', [$this, 'handle_ai_request']);
        add_filter('the_content', [$this, 'enhance_content_with_ai']);
    }

    /**
     * Создает или получает ассистента для плагина
     */
    private function get_or_create_assistant() {
        $assistant_name = 'My Plugin Assistant';

        // Проверяем, существует ли уже наш ассистент
        $existing_assistant = $this->find_assistant_by_name($assistant_name);

        if ($existing_assistant) {
            return $existing_assistant->id;
        }

        // Создаем нового ассистента
        try {
            return $this->ai_engine->create_assistant([
                'name' => $assistant_name,
                'system_prompt' => 'Ты помогаешь пользователям работать с плагином My AI Plugin. ' .
                                   'Отвечай вежливо и информативно.',
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'history_enabled' => true,
                'history_messages_count' => 10
            ]);
        } catch (Exception $e) {
            error_log('Failed to create assistant: ' . $e->getMessage());
            return 0; // Используем ассистента по умолчанию
        }
    }

    /**
     * Обработчик AJAX запросов к ИИ
     */
    public function handle_ai_request() {
        check_ajax_referer('my_plugin_ai_nonce', 'nonce');

        $user_message = sanitize_textarea_field($_POST['message']);
        $context = sanitize_textarea_field($_POST['context'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? 'default_session');

        try {
            $response = $this->ai_engine->chat_completion([
                'assistant_id' => $this->assistant_id,
                'message' => $context ? "$context\n\n$user_message" : $user_message,
                'session_id' => $this->plugin_id . '_' . $session_id,
                'plugin_id' => $this->plugin_id,
                'override_params' => [
                    'temperature' => 0.7,
                    'max_tokens' => 1500
                ]
            ]);

            wp_send_json_success([
                'response' => $response,
                'session_id' => $session_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'AI Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Пример: улучшение контента с помощью ИИ
     */
    public function enhance_content_with_ai($content) {
        if (!is_single() || !$this->should_enhance_content()) {
            return $content;
        }

        // Генерируем краткое содержание
        try {
            $summary = $this->ai_engine->chat_completion([
                'assistant_id' => $this->assistant_id,
                'message' => "Создай краткое содержание этого текста (3-4 предложения):\n\n$content",
                'session_id' => 'post_' . get_the_ID(),
                'plugin_id' => $this->plugin_id,
                'override_params' => [
                    'temperature' => 0.3,
                    'max_tokens' => 200
                ]
            ]);

            $enhanced_content = '<div class="ai-summary"><h3>Краткое содержание:</h3>' .
                                wp_kses_post($summary) . '</div>' . $content;

            return $enhanced_content;

        } catch (Exception $e) {
            // В случае ошибки возвращаем оригинальный контент
            return $content;
        }
    }

    /**
     * Находит ассистента по имени
     */
    private function find_assistant_by_name($name) {
        // Этот метод зависит от реализации AI Engine
        // В реальном плагине используйте методы API AI Engine
        return false;
    }

    /**
     * Показывает уведомление об отсутствии зависимости
     */
    public function show_missing_dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>My AI Plugin:</strong>
                <?php _e('Для работы плагина требуется RDS AI Engine.', 'my-ai-plugin'); ?>
                <a href="<?php echo admin_url('plugin-install.php?s=RDS+AI+Engine&tab=search&type=term'); ?>">
                    <?php _e('Установить RDS AI Engine', 'my-ai-plugin'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Проверяет, нужно ли улучшать контент
     */
    private function should_enhance_content() {
        return apply_filters('my_plugin_should_enhance_content',
            get_option('my_plugin_enable_ai_enhancement', true)
        );
    }
}

// Инициализация плагина
add_action('plugins_loaded', function() {
    new My_AI_Plugin();
});
```

### Шаблон 2: Расширенная интеграция с несколькими ассистентами

```php
<?php
/**
 * Продвинутый пример с несколькими ассистентами и кешированием
 */

class Advanced_AI_Integration {

    private $ai_engine;
    private $assistants = [];
    private $cache_group = 'ai_responses';

    public function __construct() {
        if (!class_exists('RDS_AIE_Main')) {
            return;
        }

        $this->ai_engine = RDS_AIE_Main::get_instance();
        $this->init_assistants();
        $this->setup_hooks();
    }

    private function init_assistants() {
        $this->assistants = [
            'summarizer' => [
                'id' => $this->get_assistant_id('Summarizer Assistant'),
                'description' => 'Создает краткие содержания текстов'
            ],
            'translator' => [
                'id' => $this->get_assistant_id('Translator Assistant'),
                'description' => 'Переводит текст между языками'
            ],
            'proofreader' => [
                'id' => $this->get_assistant_id('Proofreader Assistant'),
                'description' => 'Исправляет грамматику и стиль'
            ],
            'idea_generator' => [
                'id' => $this->get_assistant_id('Idea Generator'),
                'description' => 'Генерирует идеи для контента'
            ]
        ];
    }

    private function get_assistant_id($name) {
        // В реальной реализации получаем ID из настроек или создаем ассистента
        $assistant_map = [
            'Summarizer Assistant' => 1,
            'Translator Assistant' => 2,
            'Proofreader Assistant' => 3,
            'Idea Generator' => 4
        ];

        return $assistant_map[$name] ?? 0;
    }

    /**
     * Умное кеширование ИИ-ответов
     */
    private function get_cached_response($key, $callback, $expiration = HOUR_IN_SECONDS) {
        $cache_key = 'ai_response_' . md5($key);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $response = call_user_func($callback);

        if (!is_wp_error($response)) {
            wp_cache_set($cache_key, $response, $this->cache_group, $expiration);
        }

        return $response;
    }

    /**
     * Создает краткое содержание с кешированием
     */
    public function summarize_text($text, $max_length = 150) {
        $cache_key = "summary_" . md5($text) . "_" . $max_length;

        return $this->get_cached_response($cache_key, function() use ($text, $max_length) {
            try {
                return $this->ai_engine->chat_completion([
                    'assistant_id' => $this->assistants['summarizer']['id'],
                    'message' => "Создай краткое содержание (максимум {$max_length} слов):\n\n{$text}",
                    'session_id' => 'summarize_' . md5($text),
                    'override_params' => [
                        'temperature' => 0.3,
                        'max_tokens' => $max_length * 2
                    ]
                ]);
            } catch (Exception $e) {
                return new WP_Error('ai_error', $e->getMessage());
            }
        });
    }

    /**
     * Переводит текст с сохранением контекста
     */
    public function translate_text($text, $target_language, $source_language = 'auto') {
        try {
            $response = $this->ai_engine->chat_completion([
                'assistant_id' => $this->assistants['translator']['id'],
                'message' => "Переведи следующий текст с {$source_language} на {$target_language}. " .
                            "Сохрани тон и стиль оригинала:\n\n{$text}",
                'session_id' => 'translate_' . md5($text . $target_language),
                'override_params' => [
                    'temperature' => 0.2, // Низкая температура для точности перевода
                    'max_tokens' => strlen($text) * 2
                ]
            ]);

            return $response;

        } catch (Exception $e) {
            error_log('Translation error: ' . $e->getMessage());
            return $text; // Возвращаем оригинальный текст в случае ошибки
        }
    }

    /**
     * Генерирует идеи для контента
     */
    public function generate_content_ideas($topic, $count = 5, $style = 'blog_post') {
        try {
            $response = $this->ai_engine->chat_completion([
                'assistant_id' => $this->assistants['idea_generator']['id'],
                'message' => "Сгенерируй {$count} идей для контента на тему '{$topic}'. " .
                            "Формат: {$style}. Представь в виде нумерованного списка.",
                'session_id' => 'ideas_' . md5($topic . $style),
                'override_params' => [
                    'temperature' => 0.8, // Высокая температура для креативности
                    'max_tokens' => 1000
                ]
            ]);

            // Парсим ответ на отдельные идеи
            return $this->parse_ideas_from_response($response, $count);

        } catch (Exception $e) {
            return [];
        }
    }

    private function parse_ideas_from_response($response, $expected_count) {
        $ideas = [];
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            if (preg_match('/^\d+[\.\)]\s*(.+)$/', $line, $matches)) {
                $ideas[] = trim($matches[1]);
            } elseif (!empty(trim($line)) && count($ideas) < $expected_count) {
                $ideas[] = trim($line);
            }

            if (count($ideas) >= $expected_count) {
                break;
            }
        }

        return $ideas;
    }

    /**
     * Пакетная обработка текстов
     */
    public function batch_process_texts($texts, $operation = 'summarize', $batch_size = 5) {
        $results = [];
        $batches = array_chunk($texts, $batch_size);

        foreach ($batches as $batch) {
            $batch_results = $this->process_batch($batch, $operation);
            $results = array_merge($results, $batch_results);

            // Пауза между батчами для избежания rate limits
            sleep(1);
        }

        return $results;
    }

    private function process_batch($batch, $operation) {
        $results = [];

        foreach ($batch as $text) {
            switch ($operation) {
                case 'summarize':
                    $results[] = $this->summarize_text($text);
                    break;
                case 'translate':
                    $results[] = $this->translate_text($text, 'ru');
                    break;
                case 'proofread':
                    $results[] = $this->proofread_text($text);
                    break;
            }
        }

        return $results;
    }

    private function proofread_text($text) {
        try {
            return $this->ai_engine->chat_completion([
                'assistant_id' => $this->assistants['proofreader']['id'],
                'message' => "Исправь грамматические, орфографические и стилистические ошибки " .
                            "в следующем тексте. Верни исправленный текст:\n\n{$text}",
                'session_id' => 'proofread_' . md5($text),
                'override_params' => [
                    'temperature' => 0.1, // Минимальная креативность для корректуры
                    'max_tokens' => strlen($text) * 1.2
                ]
            ]);
        } catch (Exception $e) {
            return $text;
        }
    }

    private function setup_hooks() {
        // Регистрируем шорткоды
        add_shortcode('ai_summary', [$this, 'shortcode_summary']);
        add_shortcode('ai_translate', [$this, 'shortcode_translate']);

        // Добавляем AJAX обработчики
        add_action('wp_ajax_advanced_ai_request', [$this, 'handle_ajax_request']);
        add_action('wp_ajax_nopriv_advanced_ai_request', [$this, 'handle_ajax_request']);

        // Интеграция с редактором
        add_filter('mce_buttons', [$this, 'add_editor_buttons']);
        add_filter('mce_external_plugins', [$this, 'add_editor_plugins']);
    }

    public function shortcode_summary($atts, $content = null) {
        $atts = shortcode_atts([
            'max_length' => 100,
            'show_original' => false
        ], $atts);

        if (empty($content)) {
            return '';
        }

        $summary = $this->summarize_text($content, $atts['max_length']);

        if (is_wp_error($summary)) {
            return '<div class="ai-error">Ошибка создания краткого содержания</div>';
        }

        $output = '<div class="ai-summary">' . wp_kses_post($summary) . '</div>';

        if ($atts['show_original']) {
            $output .= '<details class="ai-original"><summary>Оригинальный текст</summary>' .
                      wp_kses_post($content) . '</details>';
        }

        return $output;
    }

    public function handle_ajax_request() {
        check_ajax_referer('advanced_ai_nonce', 'nonce');

        $action = sanitize_text_field($_POST['action_type']);
        $data = $_POST['data'] ?? [];

        switch ($action) {
            case 'summarize':
                $response = $this->summarize_text($data['text'], $data['max_length'] ?? 150);
                break;
            case 'translate':
                $response = $this->translate_text($data['text'], $data['target_lang'], $data['source_lang'] ?? 'auto');
                break;
            case 'generate_ideas':
                $response = $this->generate_content_ideas($data['topic'], $data['count'] ?? 5, $data['style'] ?? 'blog_post');
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
                return;
        }

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        } else {
            wp_send_json_success(['response' => $response]);
        }
    }
}
```

## JavaScript интеграция

### Шаблон 3: Frontend интеграция с React/JavaScript

```javascript
// ai-integration.js
class AIIntegrationClient {
  constructor(options = {}) {
    this.options = {
      ajaxUrl: window.ajaxurl || "/wp-admin/admin-ajax.php",
      nonce: "",
      pluginId: "my_ai_plugin",
      defaultAssistantId: 1,
      ...options,
    };

    this.sessionId = this.generateSessionId();
    this.conversationHistory = [];
    this.isProcessing = false;
  }

  generateSessionId() {
    return `${this.options.pluginId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  async sendRequest(action, data = {}, assistantId = null) {
    if (this.isProcessing) {
      throw new Error("Another request is already in progress");
    }

    this.isProcessing = true;

    try {
      const response = await fetch(this.options.ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: `${this.options.pluginId}_ai_request`,
          nonce: this.options.nonce,
          assistant_id: assistantId || this.options.defaultAssistantId,
          session_id: this.sessionId,
          action_type: action,
          data: JSON.stringify(data),
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Unknown error");
      }

      return result.data;
    } catch (error) {
      console.error("AI Request failed:", error);
      throw error;
    } finally {
      this.isProcessing = false;
    }
  }

  // Методы высокого уровня
  async summarize(text, maxLength = 150) {
    return this.sendRequest("summarize", {
      text: text,
      max_length: maxLength,
    });
  }

  async translate(text, targetLang, sourceLang = "auto") {
    return this.sendRequest("translate", {
      text: text,
      target_lang: targetLang,
      source_lang: sourceLang,
    });
  }

  async proofread(text) {
    return this.sendRequest("proofread", { text: text });
  }

  async generateIdeas(topic, count = 5, style = "blog_post") {
    return this.sendRequest("generate_ideas", {
      topic: topic,
      count: count,
      style: style,
    });
  }

  async chat(message, context = "", temperature = 0.7) {
    const response = await this.sendRequest("chat", {
      message: message,
      context: context,
      temperature: temperature,
    });

    // Сохраняем в историю
    this.conversationHistory.push({
      role: "user",
      content: message,
      timestamp: new Date().toISOString(),
    });

    this.conversationHistory.push({
      role: "assistant",
      content: response.response,
      timestamp: new Date().toISOString(),
    });

    // Ограничиваем историю
    if (this.conversationHistory.length > 20) {
      this.conversationHistory = this.conversationHistory.slice(-20);
    }

    return response;
  }

  getHistory() {
    return [...this.conversationHistory];
  }

  clearHistory() {
    this.conversationHistory = [];
    this.sessionId = this.generateSessionId();
  }

  // Stream обработка (для длинных ответов)
  async *streamChat(message, assistantId = null) {
    const response = await fetch(this.options.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: `${this.options.pluginId}_ai_stream`,
        nonce: this.options.nonce,
        assistant_id: assistantId || this.options.defaultAssistantId,
        session_id: this.sessionId,
        message: message,
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

// Пример использования с React
class AIComponent extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      input: "",
      response: "",
      loading: false,
      history: [],
    };

    this.aiClient = new AIIntegrationClient({
      nonce: window.myPluginNonce,
      defaultAssistantId: window.defaultAssistantId,
    });
  }

  handleSubmit = async (e) => {
    e.preventDefault();

    if (!this.state.input.trim() || this.state.loading) {
      return;
    }

    this.setState({ loading: true, response: "" });

    try {
      const result = await this.aiClient.chat(this.state.input);

      this.setState({
        response: result.response,
        input: "",
        history: this.aiClient.getHistory(),
      });
    } catch (error) {
      this.setState({
        response: `Ошибка: ${error.message}`,
        history: this.aiClient.getHistory(),
      });
    } finally {
      this.setState({ loading: false });
    }
  };

  handleSummarize = async () => {
    const text = this.state.input || this.props.content;

    if (!text.trim()) {
      return;
    }

    this.setState({ loading: true, response: "" });

    try {
      const result = await this.aiClient.summarize(text);
      this.setState({ response: result.response });
    } catch (error) {
      this.setState({ response: `Ошибка: ${error.message}` });
    } finally {
      this.setState({ loading: false });
    }
  };

  render() {
    return (
      <div className="ai-component">
        <form onSubmit={this.handleSubmit}>
          <textarea
            value={this.state.input}
            onChange={(e) => this.setState({ input: e.target.value })}
            placeholder="Введите ваш запрос..."
            disabled={this.state.loading}
          />

          <div className="button-group">
            <button type="submit" disabled={this.state.loading}>
              {this.state.loading ? "Обработка..." : "Отправить"}
            </button>

            <button
              type="button"
              onClick={this.handleSummarize}
              disabled={this.state.loading}>
              Краткое содержание
            </button>

            <button type="button" onClick={() => this.aiClient.clearHistory()}>
              Очистить историю
            </button>
          </div>
        </form>

        {this.state.response && (
          <div className="ai-response">
            <h3>Ответ ИИ:</h3>
            <div dangerouslySetInnerHTML={{ __html: this.state.response }} />
          </div>
        )}

        {this.state.history.length > 0 && (
          <div className="ai-history">
            <h3>История диалога:</h3>
            <ul>
              {this.state.history.map((msg, index) => (
                <li key={index} className={`message-${msg.role}`}>
                  <strong>{msg.role === "user" ? "Вы" : "ИИ"}:</strong>
                  <div>{msg.content}</div>
                  <small>{new Date(msg.timestamp).toLocaleTimeString()}</small>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    );
  }
}
```

## Интеграция с WordPress REST API

### Шаблон 4: REST API endpoints

```php
<?php
/**
 * REST API интеграция для AI функционала
 */

class AI_REST_API_Integration {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Эндпоинт для генерации контента
        register_rest_route('my-ai-plugin/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'prompt' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'assistant_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1
                ],
                'temperature' => [
                    'required' => false,
                    'type' => 'number',
                    'default' => 0.7
                ],
                'max_tokens' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1000
                ]
            ]
        ]);

        // Эндпоинт для пакетной обработки
        register_rest_route('my-ai-plugin/v1', '/batch-process', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_batch_process'],
            'permission_callback' => [$this, 'check_permissions']
        ]);

        // Эндпоинт для получения истории
        register_rest_route('my-ai-plugin/v1', '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_history'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }

    public function handle_generate($request) {
        try {
            $ai_engine = RDS_AIE_Main::get_instance();

            $response = $ai_engine->chat_completion([
                'assistant_id' => $request['assistant_id'],
                'message' => $request['prompt'],
                'session_id' => 'api_' . wp_generate_uuid4(),
                'plugin_id' => 'my_ai_plugin',
                'override_params' => [
                    'temperature' => $request['temperature'],
                    'max_tokens' => $request['max_tokens']
                ]
            ]);

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'content' => $response,
                    'tokens_used' => $this->estimate_tokens($response),
                    'timestamp' => current_time('mysql')
                ]
            ]);

        } catch (Exception $e) {
            return new WP_Error(
                'ai_generation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function handle_batch_process($request) {
        $items = $request->get_json_params();
        $results = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $ai_engine = RDS_AIE_Main::get_instance();

                $result = $ai_engine->chat_completion([
                    'assistant_id' => $item['assistant_id'] ?? 1,
                    'message' => $item['prompt'],
                    'session_id' => 'batch_' . $index . '_' . time(),
                    'plugin_id' => 'my_ai_plugin'
                ]);

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'result' => $result
                ];

            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }

            // Задержка между запросами
            usleep(100000); // 0.1 секунда
        }

        return rest_ensure_response([
            'processed' => count($results),
            'errors' => count($errors),
            'results' => $results,
            'errors_list' => $errors
        ]);
    }

    private function estimate_tokens($text) {
        // Простая оценка токенов (примерно 4 символа = 1 токен)
        return ceil(strlen($text) / 4);
    }

    public function check_permissions($request) {
        // Проверяем capability или другой метод аутентификации
        return current_user_can('edit_posts') ||
               wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
    }
}

// Инициализация REST API
add_action('init', function() {
    new AI_REST_API_Integration();
});
```

## Интеграция с редактором Gutenberg

### Шаблон 5: Gutenberg блок с AI

```javascript
// gutenberg-ai-block.js
const { registerBlockType } = wp.blocks;
const { createElement } = wp.element;
const { InspectorControls, RichText, MediaUpload } = wp.blockEditor;
const { PanelBody, TextControl, Button, SelectControl, Spinner } =
  wp.components;
const { withState } = wp.compose;

registerBlockType("my-plugin/ai-content", {
  title: "AI Content Block",
  icon: "admin-comments",
  category: "common",

  attributes: {
    prompt: {
      type: "string",
      default: "",
    },
    content: {
      type: "string",
      default: "",
    },
    assistantId: {
      type: "number",
      default: 1,
    },
    tone: {
      type: "string",
      default: "professional",
    },
    length: {
      type: "string",
      default: "medium",
    },
  },

  edit: withState({
    isGenerating: false,
    error: null,
    assistants: [],
  })(
    ({
      attributes,
      setAttributes,
      setState,
      isGenerating,
      error,
      assistants,
    }) => {
      const generateContent = async () => {
        if (!attributes.prompt.trim()) {
          setState({ error: "Please enter a prompt" });
          return;
        }

        setState({ isGenerating: true, error: null });

        try {
          const response = await wp.apiFetch({
            path: "/my-ai-plugin/v1/generate",
            method: "POST",
            data: {
              prompt: this.buildFullPrompt(attributes),
              assistant_id: attributes.assistantId,
              temperature: this.getTemperature(attributes.tone),
              max_tokens: this.getMaxTokens(attributes.length),
            },
          });

          if (response.success) {
            setAttributes({ content: response.data.content });
          } else {
            setState({ error: response.message || "Generation failed" });
          }
        } catch (err) {
          setState({ error: err.message });
        } finally {
          setState({ isGenerating: false });
        }
      };

      const buildFullPrompt = (attrs) => {
        let prompt = attrs.prompt;

        if (attrs.tone !== "neutral") {
          prompt += `\n\nTone: ${attrs.tone}`;
        }

        if (attrs.length !== "medium") {
          prompt += `\n\nLength: ${attrs.length}`;
        }

        return prompt;
      };

      const getTemperature = (tone) => {
        const temperatures = {
          formal: 0.3,
          professional: 0.5,
          friendly: 0.7,
          creative: 0.9,
        };
        return temperatures[tone] || 0.7;
      };

      const getMaxTokens = (length) => {
        const tokens = {
          short: 300,
          medium: 700,
          long: 1500,
          very_long: 3000,
        };
        return tokens[length] || 700;
      };

      return createElement(
        "div",
        { className: "ai-content-block" },
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: "AI Settings" },
            createElement(TextControl, {
              label: "Prompt",
              value: attributes.prompt,
              onChange: (value) => setAttributes({ prompt: value }),
              help: "Describe what you want AI to generate",
            }),

            createElement(SelectControl, {
              label: "Tone",
              value: attributes.tone,
              options: [
                { label: "Formal", value: "formal" },
                { label: "Professional", value: "professional" },
                { label: "Friendly", value: "friendly" },
                { label: "Creative", value: "creative" },
              ],
              onChange: (value) => setAttributes({ tone: value }),
            }),

            createElement(SelectControl, {
              label: "Length",
              value: attributes.length,
              options: [
                { label: "Short", value: "short" },
                { label: "Medium", value: "medium" },
                { label: "Long", value: "long" },
                { label: "Very Long", value: "very_long" },
              ],
              onChange: (value) => setAttributes({ length: value }),
            }),

            createElement(
              Button,
              {
                isPrimary: true,
                onClick: generateContent,
                disabled: isGenerating || !attributes.prompt.trim(),
              },
              isGenerating ? "Generating..." : "Generate Content",
            ),
          ),
        ),

        error && createElement("div", { className: "error-notice" }, error),

        isGenerating &&
          createElement(
            "div",
            { className: "generating-overlay" },
            createElement(Spinner, null),
            "AI is generating content...",
          ),

        createElement(RichText, {
          tagName: "div",
          value: attributes.content,
          onChange: (value) => setAttributes({ content: value }),
          placeholder: "AI generated content will appear here...",
          className: "ai-generated-content",
        }),
      );
    },
  ),

  save: ({ attributes }) => {
    return createElement(RichText.Content, {
      tagName: "div",
      value: attributes.content,
      className: "ai-generated-content",
    });
  },
});
```

## Лучшие практики и рекомендации

### 1. Обработка ошибок

```php
class AI_Error_Handler {

    public static function handle_exception(Exception $e, $context = '') {
        $error_data = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ];

        // Логируем ошибку
        error_log('AI Error: ' . json_encode($error_data));

        // Отправляем в Sentry или другой мониторинг
        if (class_exists('Raven_Client')) {
            $client = new Raven_Client(SENTRY_DSN);
            $client->captureException($e, ['extra' => $error_data]);
        }

        // Показываем понятное сообщение пользователю
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return new WP_Error(
                'ai_error',
                sprintf('AI Service Error: %s', $e->getMessage())
            );
        } else {
            return new WP_Error(
                'ai_error',
                'Sorry, the AI service is temporarily unavailable. Please try again later.'
            );
        }
    }

    public static function rate_limit_check($user_id) {
        $transient_key = 'ai_rate_limit_' . $user_id;
        $requests = get_transient($transient_key) ?: 0;

        if ($requests > 50) { // 50 запросов в час
            throw new Exception('Rate limit exceeded. Please try again later.');
        }

        set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
    }
}
```

### 2. Кеширование и оптимизация

```php
class AI_Cache_Manager {

    private $cache_group = 'ai_responses';
    private $default_ttl = HOUR_IN_SECONDS;

    public function get_cached_response($key, $generator_callback, $ttl = null) {
        $cache_key = $this->generate_cache_key($key);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        $response = call_user_func($generator_callback);

        if (!is_wp_error($response)) {
            wp_cache_set(
                $cache_key,
                $response,
                $this->cache_group,
                $ttl ?: $this->default_ttl
            );
        }

        return $response;
    }

    public function invalidate_cache($pattern) {
        global $wp_object_cache;

        if (method_exists($wp_object_cache, 'delete_by_group')) {
            $wp_object_cache->delete_by_group($this->cache_group);
        }
    }

    private function generate_cache_key($input) {
        return 'ai_' . md5(serialize($input));
    }
}
```

### 3. Безопасность

```php
class AI_Security_Manager {

    public static function sanitize_input($input) {
        // Удаляем потенциально опасные конструкции
        $input = preg_replace('/<\s*script.*?>.*?<\s*\/\s*script.*?>/is', '', $input);
        $input = preg_replace('/on\w+\s*=\s*"[^"]*"/i', '', $input);
        $input = preg_replace('/on\w+\s*=\s*\'[^\']*\'/i', '', $input);
        $input = preg_replace('/on\w+\s*=\s*[^\s>]+/i', '', $input);

        // Ограничиваем длину
        $max_length = apply_filters('ai_max_input_length', 10000);
        if (strlen($input) > $max_length) {
            $input = substr($input, 0, $max_length);
        }

        return wp_kses_post($input);
    }

    public static function validate_api_response($response) {
        if (!is_string($response)) {
            return false;
        }

        // Проверяем на потенциально опасный контент
        $dangerous_patterns = [
            '/<script.*?>.*?<\/script>/is',
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return false;
            }
        }

        return true;
    }
}
```

### 4. Мониторинг и аналитика

```php
class AI_Analytics {

    public static function track_request($params, $response_time, $success) {
        $data = [
            'timestamp' => current_time('mysql'),
            'assistant_id' => $params['assistant_id'] ?? 0,
            'model_id' => $params['model_id'] ?? 0,
            'plugin_id' => $params['plugin_id'] ?? 'unknown',
            'session_id' => $params['session_id'] ?? '',
            'response_time' => $response_time,
            'success' => $success,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        // Сохраняем в лог
        self::log_to_file($data);

        // Отправляем в Google Analytics (если настроено)
        if (function_exists('ga_send_event')) {
            ga_send_event('ai_request', $success ? 'success' : 'error', $params['plugin_id'], $response_time);
        }
    }

    private static function log_to_file($data) {
        $log_dir = WP_CONTENT_DIR . '/ai-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/requests-' . date('Y-m-d') . '.log';
        $log_entry = json_encode($data) . PHP_EOL;

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
```

## Заключение

Интеграция с **RDS AI Engine** позволяет разработчикам быстро добавлять AI-функционал в свои WordPress плагины без необходимости:

1. Реализовывать собственные механизмы работы с API
2. Управлять ключами и настройками подключения
3. Создавать систему истории диалогов
4. Обеспечивать безопасность хранения данных

Используя представленные шаблоны и лучшие практики, вы можете создавать мощные AI-инструменты для различных задач: от генерации контента до анализа данных и автоматизации процессов.
