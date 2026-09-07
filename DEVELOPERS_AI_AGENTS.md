# RDS AI Engine — Developer Documentation

**RDS AI Engine** — это модульное ядро для создания AI-агентов в WordPress. Плагин реализует принцип *«smart core — dumb peripherals»*: вся сложная логика (цикл агента, управление памятью, RAG) сосредоточена в ядре, а сторонние плагины подключаются как простые периферийные модули через инструменты (Tools) и навыки (Skills).

## 🏗 Архитектура

Плагин построен на следующих ключевых компонентах:

| Компонент | Класс | Назначение |
| :--- | :--- | :--- |
| **Main** | `RDS_AIE_Main` | Singleton. Точка входа. Управляет инициализацией, хуками и админкой. |
| **DB** | `RDS_AIE_DB` | Абстракция над `$wpdb`. Все CRUD-операции с таблицами плагина. |
| **Model Manager** | `RDS_AIE_Model_Manager` | Управление моделями (текст, изображения, эмбеддинги). |
| **Tool Registry** | `RDS_AIE_Tool_Registry` | Реестр инструментов. Хранит схемы и callback'и для выполнения действий. |
| **Skill Manager** | `RDS_AIE_Skill_Manager` | Управление навыками (инструкциями). Хранит метаданные в БД, контент в файлах/URL. |
| **Agent Engine** | `RDS_AIE_Agent_Engine` | Цикл работы агента: LLM → Tool Call → Execution → Response. |
| **History Manager** | `RDS_AIE_History_Manager` | Управление историей диалогов, суммаризация длинных сессий. |
| **RAG Engine** | `RDS_AIE_RAG_Engine` | Разбиение текста на чанки, генерация эмбеддингов, векторный поиск. |

---

##  Функционал ядра

### 1. Модели (Models)
Поддержка любых OpenAI-compatible провайдеров (OpenRouter, DeepSeek, Qwen и др.).
*   **Типы моделей:** `text`, `image`, `embedding`, `both`.
*   **Хранение:** Таблица `rds_aie_models`.
*   **Особенности:** Для embedding-моделей отдельно указывается размерность вектора (`embedding_dims`).

### 2. Ассистенты и Агенты
*   **Assistants:** Статические конфигурации системных промптов и настроек.
*   **Agents:** Активные сущности с привязанными инструментами, навыками и лимитами итераций. Поддерживают циклическое выполнение задач через Function Calling.

### 3. Инструменты (Tools)
Исполняемые функции, которые агент вызывает для взаимодействия с внешним миром.
*   **Базовые:** `wp_search_posts`, `wp_get_post`.
*   **RAG:** `search_knowledge_base` (автоматически доступен при наличии документов в БЗ).
*   **Skills:** `read_skill` (автоматически доступен, если у агента есть привязанные навыки).

### 4. Навыки (Skills)
Статические знания и инструкции для агента.
*   **Формат:** Markdown-файлы с YAML Front Matter (`name`, `description`).
*   **Хранение:** Метаданные в БД (`rds_aie_skills`), контент по URL или локальному пути.
*   **Загрузка:** Через медиатеку WordPress (разрешены `.md` файлы) или ручной ввод пути.
*   **Активация:** Агент сам решает, когда загрузить навык через инструмент `read_skill`.

### 5. База знаний (RAG)
Семантический поиск по загруженным документам.
*   **Индексация:** Ручная загрузка текста → разбиение на чанки (с overlap) → генерация векторов.
*   **Хранение:** Таблица `rds_aie_knowledge_base` (векторы хранятся как JSON в LONGTEXT).
*   **Поиск:** Косинусное сходство + опциональный гибридный буст по ключевым словам.
*   **Управление:** Группировка по `document_id` позволяет обновлять/удалять документы целиком.

### 6. Память и Суммаризация
*   История хранится в `rds_aie_conversation_messages`.
*   Автоматическая суммаризация при достижении лимита сообщений для экономии контекста.
*   Сохранение метаданных: `tool_calls`, `reasoning_content`, `tool_call_id`.

---

## 🔌 Интеграция для разработчиков

### 1. Регистрация собственных инструментов (Tools)

Любой плагин может добавить свой инструмент, который станет доступен всем агентам.

```php
add_action('rds_aie_register_tools', function($registry) {
    $registry->register_tool([
        'name' => 'my_custom_tool',
        'description' => 'Does something specific for my plugin.',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'First parameter']
            ],
            'required' => ['param1']
        ],
        'callback' => function($args) {
            // Ваша логика
            return ['result' => 'Success: ' . $args['param1']];
        }
    ]);
});
```

> **Важно:** Callback должен возвращать массив или строку. Если возвращается массив, он автоматически конвертируется в JSON для отправки в LLM.

### 2. Регистрация навыков (Skills)

Навыки можно регистрировать программно при активации плагина:

```php
register_activation_hook(__FILE__, function() {
    if (function_exists('rds_aie_register_skill')) {
        rds_aie_register_skill([
            'name' => 'my_plugin_guide',
            'description' => 'Instructions on how to use My Plugin features.',
            'url' => plugin_dir_path(__FILE__) . 'assets/skills/my-plugin-guide.md'
        ]);
    }
});
```

Файл `my-plugin-guide.md` должен иметь структуру:
```markdown
---
name: my_plugin_guide
description: Instructions on how to use My Plugin features.
version: 1.0
---

# My Plugin Usage Guide
...
```

### 3. Добавление контента в RAG из других плагинов

Если ваш плагин создает контент (например, товары, курсы, документы), вы можете автоматически индексировать его в базу знаний RDS AI Engine:

```php
// После сохранения вашего кастомного поста/записи
add_action('save_post_my_custom_type', function($post_id, $post, $update) {
    if ($post->post_status !== 'publish') return;
    
    if (class_exists('RDS_AIE_RAG_Engine') && class_exists('RDS_AIE_Main')) {
        try {
            $main = RDS_AIE_Main::get_instance();
            $rag = $main->get_rag_engine();
            
            // Генерируем уникальный ID документа на основе ID поста
            $doc_id = 'my_plugin_post_' . $post_id;
            
            // Индексируем контент
            $rag->index_text(
                $post->post_content, 
                'my_plugin',           // source_name
                $post->post_title,     // title
                $doc_id                // document_id (для обновления существующего)
            );
        } catch (Exception $e) {
            error_log('RAG Indexing Error: ' . $e->getMessage());
        }
    }
}, 10, 3);
```

### 4. Программный запуск агента

Вы можете вызвать агента из своего кода (например, в REST API endpoint или шорткоде):

```php
try {
    $main = RDS_AIE_Main::get_instance();
    $db = $main->get_db();
    
    $engine = new RDS_AIE_Agent_Engine($db);
    
    $response = $engine->run(
        $agent_id,      // ID агента из таблицы rds_aie_agents
        $user_message,  // Текст запроса пользователя
        $session_id     // Уникальный ID сессии (генерируйте сами или используйте get_current_user_id())
    );
    
    echo esc_html($response);
    
} catch (Exception $e) {
    error_log('Agent Run Error: ' . $e->getMessage());
}
```

### 5. Получение данных через публичные методы Main

Для доступа к менеджерам используйте геттеры главного класса:

```php
$main = RDS_AIE_Main::get_instance();

// Менеджеры
$model_manager = $main->get_model_manager();
$skill_manager = $main->get_skill_manager(); // Если добавлен геттер
$rag_engine    = $main->get_rag_engine();
$db            = $main->get_db();

// Пример: получить все embedding-модели
$models = $model_manager->get_embedding_models();
```

---

##  Доступные хуки

| Хук | Тип | Описание |
| :--- | :--- | :--- |
| `rds_aie_register_tools` | Action | Передача экземпляра `Tool_Registry` для регистрации инструментов. |
| `rds_aie_register_skill_paths` | Filter | Массив путей к папкам со Skill-файлами (если используется файловый реестр). |
| `rds_aie_flush_skills_cache` | Action | Принудительный сброс кэша навыков. Вызывать при обновлении плагина. |

---

## ⚙️ Требования и ограничения

*   **PHP:** 7.4+
*   **WordPress:** 6.0+
*   **Shared Hosting:** Полностью совместим. Не требует Node.js, Python или внешних сервисов.
*   **Память:** Для индексации больших текстов рекомендуется `memory_limit >= 256M`. Движок RAG автоматически пытается увеличить лимит до 512M во время индексации.
*   **API:** Требуется действующий API Key от провайдера LLM (OpenRouter, OpenAI и др.) для текстовых моделей и эмбеддингов.

---

##  Структура базы данных

| Таблица | Назначение |
| :--- | :--- |
| `rds_aie_models` | Конфигурация AI-моделей |
| `rds_aie_agents` | Настройки агентов |
| `rds_aie_agent_tools` | Привязка инструментов к агентам |
| `rds_aie_skills` | Метаданные навыков |
| `rds_aie_agent_skills` | Привязка навыков к агентам |
| `rds_aie_knowledge_base` | Чанки и векторы RAG |
| `rds_aie_conversation_messages` | История диалогов и метаданные |

---
