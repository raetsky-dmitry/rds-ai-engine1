# RDS AI Engine

Базовый плагин WordPress для интеграции с ИИ-сервисами через OpenAI-совместимые API. Плагин предоставляет централизованное управление моделями, ассистентами и историей диалогов.

## Возможности

- ✅ **Управление моделями** - поддержка любых OpenAI-совместимых API (OpenAI, OpenRouter, LM Studio, Ollama и др.)
- ✅ **Управление ассистентами** - создание ассистентов с системными промптами и настройками
- ✅ **История диалогов** - автоматическое сохранение и управление историей диалогов
- ✅ **Тестовый чат** - встроенный интерфейс для тестирования конфигураций
- ✅ **Безопасность** - шифрование API ключей, проверка прав доступа
- ✅ **Мультитенантность** - изоляция истории между плагинами и пользователями

## Установка

1. Загрузите плагин в папку `/wp-content/plugins/rds-ai-engine/`
2. Активируйте плагин через меню "Плагины" WordPress
3. Перейдите в "RDS AI Engine" в админке WordPress

## Настройка

### 1. Добавление моделей (API подключения)

1. Перейдите в **RDS AI Engine → Models**
2. Нажмите "Add New Model"
3. Заполните поля:
   - **Model Name**: Имя модели (например, "OpenAI GPT-4")
   - **Base URL**: URL API (например, `https://api.openai.com/v1`)
   - **AI Model Name**: Имя модели в API (например, `gpt-4-turbo`)
   - **API Key**: Ваш API ключ
   - **Max Tokens**: Максимальное количество токенов в ответе

### 2. Создание ассистентов

1. Перейдите в **RDS AI Engine → Assistants**
2. Нажмите "Add New Assistant"
3. Настройте ассистента:
   - **Name**: Имя ассистента
   - **System Prompt**: Системный промпт, определяющий поведение
   - **Default Model**: Модель по умолчанию
   - **Temperature**: Креативность ответов (0-2)
   - **History Settings**: Настройки истории диалогов

### 3. Тестирование

1. Перейдите в **RDS AI Engine → Test Chat**
2. Выберите модель и/или ассистента
3. Начните общение

## Использование в других плагинах

### PHP Integration

#### Базовый пример

```php
// Получение экземпляра AI Engine
$ai_engine = RDS_AIE_Main::get_instance();

// Простой запрос
$response = $ai_engine->chat_completion([
    'assistant_id' => 1, // ID ассистента
    'message' => 'Привет! Как дела?',
    'override_params' => [
        'temperature' => 0.8
    ]
]);

echo $response;
```

#### Расширенный пример с историей

```php
class My_Plugin_AI_Assistant {

    private $ai_engine;
    private $plugin_id = 'my_plugin';
    private $session_id;

    public function __construct($user_id = 0) {
        $this->ai_engine = RDS_AIE_Main::get_instance();
        $this->session_id = $this->generate_session_id($user_id);
    }

    public function ask($message, $assistant_id = 1, $model_id = 0) {
        try {
            $response = $this->ai_engine->chat_completion([
                'assistant_id' => $assistant_id,
                'model_id' => $model_id,
                'message' => $message,
                'session_id' => $this->session_id,
                'plugin_id' => $this->plugin_id,
                'override_params' => [
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ]
            ]);

            return [
                'success' => true,
                'response' => $response,
                'session_id' => $this->session_id
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function get_conversation_history($limit = 10) {
        $history_manager = $this->ai_engine->get_history_manager();
        return $history_manager->get_formatted_history($this->session_id, $limit);
    }

    public function clear_history() {
        $history_manager = $this->ai_engine->get_history_manager();
        return $history_manager->clear_history($this->session_id);
    }

    private function generate_session_id($user_id) {
        return 'my_plugin_' . $user_id . '_' . md5(time() . wp_rand());
    }
}

// Использование
$assistant = new My_Plugin_AI_Assistant(get_current_user_id());

// Задать вопрос
$result = $assistant->ask('Расскажи о возможностях WordPress');
if ($result['success']) {
    echo $result['response'];
}

// Получить историю
$history = $assistant->get_conversation_history(5);
```

#### Пример создания кастомных ассистентов

```php
// Простой пример создания и использования ассистента
$ai_engine = RDS_AIE_Main::get_instance();

// 1. Создаем ассистента (или получаем существующего)
$assistant_id = $ai_engine->get_or_create_assistant('My Plugin Assistant', [
    'system_prompt' => 'Ты помогаешь пользователям работать с плагином My Plugin...',
    'temperature' => 0.7,
    'max_tokens' => 1000
]);

// 2. Используем ассистента
$response = $ai_engine->chat_completion([
    'assistant_id' => $assistant_id,
    'message' => 'Как настроить плагин?',
    'session_id' => 'user_' . get_current_user_id()
]);

// Или через хелпер-функцию
$response = rds_aie_chat('Как настроить плагин?', $assistant_id);
```

#### Пример с кастомными ассистентами

```php
class Content_Generator {

    private $ai_engine;
    private $assistant_map = [
        'blog_writer' => 1,    // ID ассистента для блогов
        'seo_optimizer' => 2,  // ID ассистента для SEO
        'translator' => 3      // ID ассистента для переводов
    ];

    public function __construct() {
        $this->ai_engine = RDS_AIE_Main::get_instance();
    }

    public function generate_blog_post($topic, $tone = 'professional') {
        $prompt = "Напиши статью в блоге на тему: {$topic}\n";
        $prompt .= "Тон: {$tone}\n";
        $prompt .= "Длина: 1000 слов\n";
        $prompt .= "Формат: HTML с заголовками";

        return $this->ai_engine->chat_completion([
            'assistant_id' => $this->assistant_map['blog_writer'],
            'message' => $prompt,
            'session_id' => 'content_gen_' . md5($topic),
            'plugin_id' => 'content_generator'
        ]);
    }

    public function optimize_seo($text, $keywords) {
        $prompt = "Оптимизируй текст для SEO:\n\n";
        $prompt .= "Текст: {$text}\n\n";
        $prompt .= "Ключевые слова: " . implode(', ', $keywords) . "\n";
        $prompt .= "Сделай текст читабельным и SEO-оптимизированным.";

        return $this->ai_engine->chat_completion([
            'assistant_id' => $this->assistant_map['seo_optimizer'],
            'message' => $prompt,
            'override_params' => [
                'temperature' => 0.3, // Более консервативные ответы для SEO
                'max_tokens' => 2000
            ]
        ]);
    }
}
```

### JavaScript Integration

#### Базовый AJAX пример

```javascript
class MyPluginAIClient {

    constructor(assistantId, modelId = 0) {
        this.assistantId = assistantId;
        this.modelId = modelId;
        this.sessionId = this.generateSessionId();
        this.conversationHistory = [];
    }

    sendMessage(message) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: myPluginAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'my_plugin_ai_chat',
                    nonce: myPluginAjax.nonce,
                    message: message,
                    assistant_id: this.assistantId,
                    model_id: this.modelId,
                    session_id: this.sessionId
                },
                success: (response) => {
                    if (response.success) {
                        this.conversationHistory.push({
                            role: 'user',
                            content: message
                        });
                        this.conversationHistory.push({
                            role: 'assistant',
                            content: response.data.response
                        });
                        resolve(response.data.response);
                    } else {
                        reject(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    reject(error);
                }
            });
        });
    }

    getHistory() {
        return this.conversationHistory;
    }

    clearHistory() {
        this.conversationHistory = [];
        this.sessionId = this.generateSessionId();
    }

    generateSessionId() {
        return 'myplugin_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
}

// PHP обработчик для AJAX
add_action('wp_ajax_my_plugin_ai_chat', 'my_plugin_handle_ai_chat');
add_action('wp_ajax_nopriv_my_plugin_ai_chat', 'my_plugin_handle_ai_chat'); // Если нужно для гостей

function my_plugin_handle_ai_chat() {
    check_ajax_referer('my_plugin_nonce', 'nonce');

    $message = sanitize_textarea_field($_POST['message']);
    $assistant_id = intval($_POST['assistant_id']);
    $model_id = intval($_POST['model_id']);
    $session_id = sanitize_text_field($_POST['session_id']);

    try {
        $ai_engine = RDS_AIE_Main::get_instance();

        $response = $ai_engine->chat_completion([
            'assistant_id' => $assistant_id,
            'model_id' => $model_id,
            'message' => $message,
            'session_id' => $session_id,
            'plugin_id' => 'my_plugin'
        ]);

        wp_send_json_success([
            'response' => $response,
            'session_id' => $session_id
        ]);

    } catch (Exception $e) {
        wp_send_json_error([
            'message' => $e->getMessage()
        ]);
    }
}
```

#### Пример чата на фронтенде

```javascript
// my-plugin-chat.js
(function ($) {
  "use strict";

  class MyPluginChat {
    constructor(options) {
      this.options = $.extend(
        {
          assistantId: 1,
          modelId: 0,
          container: "#my-plugin-chat",
          input: "#chat-input",
          sendBtn: "#send-btn",
          messagesContainer: "#chat-messages",
          userAvatar: "👤",
          assistantAvatar: "🤖",
          maxHistory: 10,
        },
        options
      );

      this.aiClient = new MyPluginAIClient(
        this.options.assistantId,
        this.options.modelId
      );

      this.init();
    }

    init() {
      const self = this;

      // Отправка сообщения по клику
      $(this.options.sendBtn).on("click", function () {
        self.sendMessage();
      });

      // Отправка по Enter
      $(this.options.input).on("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          self.sendMessage();
        }
      });

      // Загрузка истории при инициализации
      this.loadHistory();
    }

    sendMessage() {
      const input = $(this.options.input);
      const message = input.val().trim();

      if (!message) return;

      // Добавляем сообщение пользователя
      this.addMessage("user", message);
      input.val("");

      // Показываем индикатор загрузки
      this.showLoading();

      // Отправляем запрос
      this.aiClient
        .sendMessage(message)
        .then((response) => {
          this.hideLoading();
          this.addMessage("assistant", response);
          this.scrollToBottom();
        })
        .catch((error) => {
          this.hideLoading();
          this.addMessage("error", "Ошибка: " + error);
          this.scrollToBottom();
        });
    }

    addMessage(role, content) {
      const container = $(this.options.messagesContainer);
      const timestamp = new Date().toLocaleTimeString();

      let avatar, cssClass;
      switch (role) {
        case "user":
          avatar = this.options.userAvatar;
          cssClass = "user-message";
          break;
        case "assistant":
          avatar = this.options.assistantAvatar;
          cssClass = "assistant-message";
          break;
        case "error":
          avatar = "❌";
          cssClass = "error-message";
          break;
      }

      const messageHtml = `
                <div class="chat-message ${cssClass}">
                    <div class="message-avatar">${avatar}</div>
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(
                          content
                        )}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;

      container.append(messageHtml);
      this.scrollToBottom();
    }

    loadHistory() {
      const history = this.aiClient.getHistory();
      history.forEach((msg) => {
        this.addMessage(msg.role, msg.content);
      });
    }

    clearChat() {
      $(this.options.messagesContainer).empty();
      this.aiClient.clearHistory();
    }

    showLoading() {
      $(this.options.messagesContainer).append(`
                <div class="chat-message loading-message">
                    <div class="message-avatar">${this.options.assistantAvatar}</div>
                    <div class="message-content">
                        <div class="loading-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            `);
      this.scrollToBottom();
    }

    hideLoading() {
      $(this.options.messagesContainer).find(".loading-message").remove();
    }

    scrollToBottom() {
      const container = $(this.options.messagesContainer);
      container.scrollTop(container[0].scrollHeight);
    }

    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML.replace(/\n/g, "<br>");
    }
  }

  // Инициализация при загрузке документа
  $(document).ready(function () {
    window.myPluginChat = new MyPluginChat({
      assistantId: myPluginChatSettings.assistantId,
      modelId: myPluginChatSettings.modelId,
      container: "#my-plugin-chat-container",
    });
  });
})(jQuery);
```

## Структура базы данных

### Основные таблицы:

- `wp_rds_aie_models` - модели/креденшиалы API
- `wp_rds_aie_assistants` - ассистенты с промптами и настройками
- `wp_rds_aie_conversations` - история диалогов

### Поля ассистента:

- `name` - имя ассистента
- `system_prompt` - системный промпт
- `default_model_id` - ID модели по умолчанию
- `max_tokens` - максимальное количество токенов
- `temperature` - температура (креативность)
- `history_enabled` - включена ли история
- `history_messages_count` - количество сообщений истории

## Фильтры и хуки

### Фильтры WordPress:

```php
// Фильтр для модификации параметров запроса перед отправкой
add_filter('rds_aie_chat_completion_params', function($params, $context) {
    // Добавляем дополнительные параметры
    $params['top_p'] = 0.9;
    $params['frequency_penalty'] = 0.2;
    return $params;
}, 10, 2);

// Фильтр для модификации сообщений перед отправкой
add_filter('rds_aie_chat_messages', function($messages, $assistant_id) {
    // Добавляем контекст к первому сообщению
    if (!empty($messages) && $messages[0]['role'] === 'user') {
        $messages[0]['content'] = "Контекст: сегодня " . date('d.m.Y') . "\n\n" . $messages[0]['content'];
    }
    return $messages;
}, 10, 2);
```

### События:

```php
// Действие после успешного ответа от ИИ
add_action('rds_aie_chat_completion_success', function($response, $params) {
    error_log('AI Response received: ' . substr($response, 0, 100));
}, 10, 2);

// Действие при ошибке
add_action('rds_aie_chat_completion_error', function($error, $params) {
    error_log('AI Error: ' . $error->getMessage());
}, 10, 2);
```

## Лучшие практики

### 1. Изоляция сессий

Всегда используйте уникальные `session_id` для разных пользователей и контекстов.

### 2. Обработка ошибок

Всегда оборачивайте вызовы в try-catch блоки.

### 3. Лимиты токенов

Учитывайте лимиты токенов моделей при работе с длинными текстами.

### 4. Кеширование

Для часто повторяющихся запросов реализуйте кеширование на уровне вашего плагина.

### 5. Безопасность

- Никогда не передавайте API ключи на клиентскую сторону
- Проверяйте nonce для всех AJAX запросов
- Ограничивайте права доступа

## Примеры использования

### 1. Плагин поддержки

```php
class Support_Bot {
    public function handle_support_request($user_message, $ticket_id) {
        $ai = RDS_AIE_Main::get_instance();

        $context = "Тикет #{$ticket_id}. История вопроса: ...";

        return $ai->chat_completion([
            'assistant_id' => 4, // ID ассистента поддержки
            'message' => $context . "\n\nВопрос пользователя: " . $user_message,
            'session_id' => 'ticket_' . $ticket_id,
            'plugin_id' => 'support_system'
        ]);
    }
}
```

### 2. Генератор контента

```php
class Social_Media_Generator {
    public function generate_post($platform, $topic, $style) {
        $ai = RDS_AIE_Main::get_instance();

        $assistant_map = [
            'twitter' => 5,
            'facebook' => 6,
            'instagram' => 7,
            'linkedin' => 8
        ];

        return $ai->chat_completion([
            'assistant_id' => $assistant_map[$platform],
            'message' => "Сгенерируй пост для {$platform} на тему '{$topic}' в стиле {$style}",
            'override_params' => [
                'temperature' => 0.9, // Более креативно для соцсетей
                'max_tokens' => 500
            ]
        ]);
    }
}
```

### 3. Анализатор текста

```php
class Text_Analyzer {
    public function analyze_sentiment($text) {
        $ai = RDS_AIE_Main::get_instance();

        $response = $ai->chat_completion([
            'assistant_id' => 9, // ID ассистента-анализатора
            'message' => "Проанализируй тональность текста и верни JSON с оценкой от -1 до 1:\n\n{$text}",
            'override_params' => [
                'temperature' => 0.1, // Минимальная креативность для анализа
                'response_format' => ['type' => 'json_object']
            ]
        ]);

        return json_decode($response, true);
    }
}
```

## Устранение неполадок

### Частые проблемы:

1. **"No AI model configured"** - не создана ни одна модель в настройках
2. **API ошибки** - проверьте URL, API ключ и доступность сервиса
3. **История не работает** - проверьте настройки ассистента (history_enabled)
4. **Медленные ответы** - увеличьте timeout в настройках модели

### Логирование:

Включите debug режим WordPress для просмотра логов:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Лицензия

GPL v2 или позднее

## Поддержка

Для вопросов и предложений создавайте issue на GitHub.

---

**RDS AI Engine** предоставляет мощную основу для интеграции ИИ в WordPress проекты. С помощью этого плагина вы можете быстро добавлять ИИ-функционал в свои плагины без необходимости реализовывать сложную инфраструктуру для работы с API.
