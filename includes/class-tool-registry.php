<?php
/**
 * Реестр инструментов для агентов RDS AI Engine
 */
class RDS_AIE_Tool_Registry {
	private static $instance = null;
	private $tools = [];

	private function __construct() {
		// Загружаем инструменты через хук
		$this->tools = apply_filters('rds_aie_register_tools', []);
		
		// Добавляем базовые инструменты WordPress
		$this->register_wp_basics();
	}

	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Регистрация базовых инструментов WP
	 */
	private function register_wp_basics() {
		// Инструмент поиска постов
		$this->tools['wp_search_posts'] = [
			'name' => 'wp_search_posts',
			'description' => 'Search for WordPress posts or pages by title or content.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'search_term' => [
						'type' => 'string',
						'description' => 'The term to search for.'
					],
					'post_type' => [
						'type' => 'string',
						'default' => 'post',
						'description' => 'Type of post to search (post, page, etc.).'
					]
				],
				'required' => ['search_term']
			],
			'callback' => [$this, 'tool_search_posts']
		];

		// Инструмент чтения поста (расширенный для маркетинга/анализа)
		$this->tools['wp_get_post'] = [
			'name' => 'wp_get_post',
			'description' => 'Get the content of a WordPress post by ID. By default returns plain text to save tokens. Set include_html=true to get raw HTML with structure (H2/H3, lists). Set include_meta=true to get SEO title/description.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [
						'type' => 'integer',
						'description' => 'The ID of the post.'
					],
					'include_html' => [
						'type' => 'boolean',
						'description' => 'Return raw HTML instead of stripped text. Use when analyzing structure or formatting. Default: false.'
					],
					'include_meta' => [
						'type' => 'boolean',
						'description' => 'Include SEO meta fields (title, description, permalink). Default: false.'
					]
				],
				'required' => ['post_id']
			],
			'callback' => [$this, 'tool_get_post']
		];

        // Инструмент создания поста (безопасное создание ченовика)
        $this->tools['wp_create_post'] = [
            'name' => 'wp_create_draft_post',
            'description' => 'Create a new WordPress post as a DRAFT. Use this when the user asks to write an article, blog post, or landing page copy. NEVER publish posts directly.',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'The title of the post.'],
                    'content' => ['type' => 'string', 'description' => 'The full HTML or Markdown content.'],
                    'post_type' => ['type' => 'string', 'description' => 'Post type (default: "post").', 'enum' => ['post', 'page']]
                ],
                'required' => ['title', 'content']
            ],
            'callback' => [__CLASS__, 'create_draft_post']
        ];

		// Инструмент обновления поста (безопасное редактирование через черновики)
		$this->tools['wp_update_post'] = [
			'name' => 'wp_update_post',
			'description' => 'Update an existing WordPress post. ALWAYS sets status to "draft" for safety, even if the post was published. Use this to edit content, titles, or SEO metadata. Never use this to publish posts directly.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [
						'type' => 'integer',
						'description' => 'The ID of the post to update.'
					],
					'title' => [
						'type' => 'string',
						'description' => 'New title for the post. Optional.'
					],
					'content' => [
						'type' => 'string',
						'description' => 'New content for the post. Supports HTML. Optional.'
					],
					'excerpt' => [
						'type' => 'string',
						'description' => 'New excerpt/summary for the post. Optional.'
					]
				],
				'required' => ['post_id']
			],
			'callback' => [$this, 'tool_update_post']
		];

		// Инструмент веб-поиска через Parallel.ai (v1 Search API)
		$this->tools['web_search'] = [
			'name' => 'web_search',
			'description' => 'Search the web for up-to-date information using Parallel.ai. Requires an "objective" (natural language goal) and "search_queries" (array of 2-3 concise keywords). Returns clean content excerpts optimized for AI analysis.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'objective' => [
						'type' => 'string',
						'description' => 'Natural-language description of the underlying question or goal driving the search. Used together with search_queries to focus results on the most relevant content. Should be self-contained with enough context to understand the intent of the search.'
					],
					'search_queries' => [
						'type' => 'array',
						'description' => 'Concise keyword search queries, 3-6 words each. At least one query is required, provide 2-3 for best results. Used together with objective to focus results on the most relevant content.',
						'items' => [
							'type' => 'string'
						],
						'minItems' => 1,
						'maxItems' => 5
					],
					'num_results' => [
						'type' => 'integer',
						'description' => 'Number of results to return (default: 5, max: 10).',
						'minimum' => 1,
						'maximum' => 10
					]
				],
				'required' => ['search_queries']
			],
			'callback' => [$this, 'tool_web_search_parallel']
		];

		// Инструмент извлечения контента через Parallel.ai Extract API
		$this->tools['web_fetch'] = [
			'name' => 'web_fetch',
			'description' => 'Extract clean, LLM-optimized markdown content from specific URLs using Parallel.ai. Use this to read full articles, analyze competitor pages, or get detailed information from links found via web_search.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'urls' => [
						'type' => 'array',
						'description' => 'List of URLs to extract content from (max 20).',
						'items' => ['type' => 'string'],
						'minItems' => 1,
						'maxItems' => 20
					],
					'objective' => [
						'type' => 'string',
						'description' => 'Natural-language description of what to extract. If provided, returns focused excerpts instead of full page. Example: "Find pricing details and service guarantees for window installation."'
					]
				],
				'required' => ['urls']
			],
			'callback' => [$this, 'tool_web_fetch_parallel']
		];		

		// Инструмент чтения навыка
		$this->tools['read_skill'] = [
			'name' => 'read_skill',
			'description' => 'Read the full content/instructions of a specific skill by its name. Use this when you need detailed guidelines or knowledge from a registered skill.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'skill_name' => [
						'type' => 'string',
						'description' => 'The unique name (slug) of the skill to read.'
					]
				],
				'required' => ['skill_name']
			],
			'callback' => [$this, 'tool_read_skill']
		];

		// Инструмент поиска по базе знаний (RAG)
		$this->tools['search_knowledge_base'] = [
			'name' => 'search_knowledge_base',
			'description' => 'Search for relevant information in the Knowledge Base. Use this when the user asks questions about specific documents, company policies, or content that has been indexed.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'query' => [
						'type' => 'string',
						'description' => 'The search query or question to find relevant chunks for.'
					]
				],
				'required' => ['query']
			],
			'callback' => [$this, 'tool_search_knowledge_base']
		];
	}


	/**
	 * Публичный метод для регистрации внешних инструментов
	 * @param array $tool_data Массив с ключами: name, description, schema, callback
	 */
	public function add_tool($tool_data) {
		if (!isset($tool_data['name'], $tool_data['description'], $tool_data['schema'], $tool_data['callback'])) {
			error_log('[RDS AI Engine] Invalid tool data provided to add_tool(). Missing required keys.');
			return false;
		}

		$name = sanitize_key($tool_data['name']);
		
		// Проверка на дубликаты
		if (isset($this->tools[$name])) {
			error_log("[RDS AI Engine] Tool '{$name}' is already registered. Overwriting.");
		}

		$this->tools[$name] = [
			'name'        => $name,
			'description' => wp_kses_post($tool_data['description']),
			'schema'      => $tool_data['schema'],
			'callback'    => $tool_data['callback']
		];

		return true;
	}
	/**
	 * Получить все зарегистрированные инструменты
	 */
	public function get_all_tools() {
		return $this->tools;
	}

	/**
	 * Выполнить инструмент по имени
	 */
	public function execute_tool($name, $arguments) {
		if (!isset($this->tools[$name])) {
			throw new Exception(sprintf(__('Tool "%s" is not registered.', 'rds-ai-engine'), $name));
		}

		$tool = $this->tools[$name];
		if (!is_callable($tool['callback'])) {
			throw new Exception(sprintf(__('Callback for tool "%s" is invalid.', 'rds-ai-engine'), $name));
		}

		return call_user_func($tool['callback'], $arguments);
	}

	// --- Callbacks для базовых инструментов ---

	public function tool_search_posts($args) {
		$search_term = isset($args['search_term']) ? sanitize_text_field($args['search_term']) : '';
		$post_type = isset($args['post_type']) ? sanitize_text_field($args['post_type']) : 'post';

		$query = new WP_Query([
			's' => $search_term,
			'post_type' => $post_type,
			'posts_per_page' => 5,
			'fields' => 'ids' // Получаем только ID для экономии памяти
		]);

		$results = [];
		if ($query->have_posts()) {
			foreach ($query->posts as $post_id) {
				$results[] = [
					'id' => $post_id,
					'title' => get_the_title($post_id),
					'excerpt' => wp_trim_words(get_the_excerpt($post_id), 20)
				];
			}
		}
		wp_reset_postdata();
		return $results;
	}

	/**
	 * Tool: Get WordPress Post Content
	 * @param array $args {
	 *     @type int    $post_id      Required. Post ID.
	 *     @type bool   $include_html Optional. Return raw HTML instead of stripped text. Default false.
	 *     @type bool   $include_meta Optional. Include SEO meta fields. Default false.
	 * }
	 */
	public function tool_get_post($args) {
		$post_id = intval($args['post_id'] ?? 0);
		if (!$post_id) {
			return ['error' => 'Valid post_id is required.'];
		}

		$post = get_post($post_id);
		if (!$post) {
			return ['error' => 'Post not found with ID: ' . $post_id];
		}

		$include_html = !empty($args['include_html']);
		$include_meta = !empty($args['include_meta']);

		$result = [
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'status'  => $post->post_status,
			'type'    => $post->post_type,
			'date'    => $post->post_date,
			'author'  => get_the_author_meta('display_name', $post->post_author),
		];

		// Контент: либо сырой HTML, либо очищенный текст
		if ($include_html) {
			$result['content'] = $post->post_content;
		} else {
			// Базовая очистка для экономии токенов при обычном использовании
			$result['content'] = wp_strip_all_tags($post->post_content);
		}

		// Мета-данные для SEO/маркетинга
		if ($include_meta) {
			$result['meta'] = [
				'seo_title'       => get_post_meta($post->ID, '_yoast_wpseo_title', true) 
				                     ?: get_post_meta($post->ID, '_rank_math_title', true) 
				                     ?: '',
				'seo_description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) 
				                     ?: get_post_meta($post->ID, '_rank_math_description', true) 
				                     ?: '',
				'permalink'       => get_permalink($post->ID),
			];
		}

		return $result;
	}


	/**
	 * Tool: Update WordPress Post (Safe Draft Mode)
	 * Always saves as draft to prevent accidental publishing.
	 */
	public function tool_update_post($args) {
		$post_id = intval($args['post_id'] ?? 0);
		if (!$post_id) {
			return ['error' => 'Valid post_id is required.'];
		}

		// Проверяем существование поста
		$post = get_post($post_id);
		if (!$post) {
			return ['error' => 'Post not found with ID: ' . $post_id];
		}

		// Собираем данные для обновления
		$update_data = [
			'ID'          => $post_id,
			'post_status' => 'draft', // КРИТИЧЕСКИ ВАЖНО: всегда сохраняем как черновик
		];

		if (!empty($args['title'])) {
			$update_data['post_title'] = sanitize_text_field($args['title']);
		}

		if (!empty($args['content'])) {
			$update_data['post_content'] = wp_kses_post($args['content']);
		}

		if (!empty($args['excerpt'])) {
			$update_data['post_excerpt'] = sanitize_textarea_field($args['excerpt']);
		}

		try {
			$result = wp_update_post($update_data, true); // true = возвращает WP_Error

			if (is_wp_error($result)) {
				error_log('[RDS AI Engine] wp_update_post failed: ' . $result->get_error_message());
				return ['error' => 'Failed to update post: ' . $result->get_error_message()];
			}

			return [
				'success'  => true,
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link($post_id, 'raw'),
				'message'  => sprintf(
					'Post #%d updated and saved as DRAFT. Review here: %s',
					$post_id,
					get_edit_post_link($post_id, 'raw')
				)
			];

		} catch (Exception $e) {
			error_log('[RDS AI Engine] Unexpected error updating post: ' . $e->getMessage());
			return ['error' => 'Unexpected error: ' . $e->getMessage()];
		}
	}

    /**
	 * Tool: Create WordPress Post (Safe Draft Mode)
	 */
	public static function create_draft_post($args) {
        if (empty($args['title']) || empty($args['content'])) {
            return ['error' => 'Both "title" and "content" are required.'];
        }

        $post_type = isset($args['post_type']) && in_array($args['post_type'], ['post', 'page']) 
                     ? $args['post_type'] : 'post';

        try {
            $post_id = wp_insert_post([
                'post_title'   => sanitize_text_field($args['title']),
                'post_content' => wp_kses_post($args['content']),
                'post_status'  => 'draft',
                'post_type'    => $post_type,
                'post_author'  => get_current_user_id()
            ], true);

            if (is_wp_error($post_id)) {
                return ['error' => 'Failed to create draft: ' . $post_id->get_error_message()];
            }

            return [
                'success'  => true,
                'post_id'  => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'message'  => sprintf(
                    'Draft "%s" created! Review here: %s',
                    sanitize_text_field($args['title']),
                    get_edit_post_link($post_id, 'raw')
                )
            ];
        } catch (Exception $e) {
            return ['error' => 'Unexpected error: ' . $e->getMessage()];
        }
    }
	

	/**
	 * Tool: Web Search via Parallel.ai API
	 * Accepts separate objective and search_queries for optimal results.
	 */
	public function tool_web_search_parallel($args) {
		// Получаем параметры поиска
		$objective = isset($args['objective']) ? sanitize_textarea_field($args['objective']) : '';
		$search_queries = isset($args['search_queries']) && is_array($args['search_queries']) 
		                  ? array_map('sanitize_text_field', $args['search_queries']) 
		                  : [];

		// Валидация: требуется хотя бы один поисковый запрос
		if (empty($search_queries)) {
			return ['error' => 'At least one search query is required in "search_queries" array.'];
		}

		// Ограничиваем количество запросов (Parallel рекомендует 2-3)
		$search_queries = array_slice($search_queries, 0, 5);

		$num_results = min(max(intval($args['num_results'] ?? 5), 1), 10);
		
		// Получаем API ключ из настроек интеграций ядра
		$integration_settings = get_option('rds_aie_integration_settings', []);
		$api_key = $integration_settings['parallel_api_key'] ?? '';

		// Fallback на константу (для dev-среды) или фильтр
		if (empty($api_key)) {
			$api_key = defined('RDS_AIE_PARALLEL_API_KEY') 
			           ? RDS_AIE_PARALLEL_API_KEY 
			           : apply_filters('rds_aie_parallel_api_key', '');
		}
		
		if (empty($api_key)) {
			return [
				'error' => 'Parallel.ai API key not configured.',
				'hint'  => 'Please add your Parallel.ai API key in RDS AI Engine → Integrations tab.'
			];
		}

		// Кэш на 2 часа (ключ включает objective для точности)
		$cache_key = 'rds_aie_search_parallel_' . md5(serialize([
			'objective'      => $objective,
			'search_queries' => $search_queries,
			'num_results'    => $num_results
		]));
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		try {
			// Формируем тело запроса СТРОГО по схеме Parallel.ai v1
			$request_body = [
				'objective'      => !empty($objective) ? $objective : null,
				'search_queries' => $search_queries,
				'mode'           => 'fast', // Баланс скорости и качества
				'advanced_settings' => [
					'max_results' => $num_results
				]
			];

			$response = wp_remote_post('https://api.parallel.ai/v1/search', [
				'timeout' => 20,
				'headers' => [
					'x-api-key'    => $api_key,
					'Content-Type' => 'application/json'
				],
				'body' => wp_json_encode($request_body)
			]);

			if (is_wp_error($response)) {
				throw new Exception($response->get_error_message());
			}

			$body = json_decode(wp_remote_retrieve_body($response), true);
			
			// Проверяем наличие ошибок от API
			if (isset($body['type']) && $body['type'] === 'error') {
				$error_msg = $body['error']['message'] ?? 'Unknown Parallel.ai error';
				if (!empty($body['error']['detail']['errors'])) {
					$error_msg .= ': ' . wp_json_encode($body['error']['detail']['errors']);
				}
				throw new Exception($error_msg);
			}

			if (!isset($body['results']) || !is_array($body['results'])) {
				throw new Exception('Invalid response format from Parallel.ai');
			}

			// Форматируем результаты для агента
			$formatted = array_map(function($r) {
				$content = implode("\n\n", $r['excerpts'] ?? []);
				
				return [
					'title'        => $r['title'] ?? '',
					'url'          => $r['url'] ?? '',
					'content'      => $content,
					'publish_date' => $r['publish_date'] ?? null,
				];
			}, array_slice($body['results'], 0, $num_results));

			$result = [
				'query'      => $search_queries[0], // Для обратной совместимости в логах
				'objective'  => $objective,
				'results'    => $formatted,
				'total'      => count($formatted),
				'session_id' => $body['session_id'] ?? ''
			];

			set_transient($cache_key, $result, 2 * HOUR_IN_SECONDS);
			return $result;

		} catch (Exception $e) {
			error_log('[RDS AI Engine] Parallel.ai search error: ' . $e->getMessage());
			return ['error' => 'Search failed: ' . $e->getMessage()];
		}
	}

	/**
	 * Tool: Web Content Extraction via Parallel.ai Extract API
	 * Returns clean markdown optimized for LLM consumption.
	 */
	public function tool_web_fetch_parallel($args) {
		$urls = isset($args['urls']) && is_array($args['urls']) 
		        ? array_map('esc_url_raw', $args['urls']) 
		        : [];
		
		if (empty($urls)) {
			return ['error' => 'At least one URL is required in "urls" array.'];
		}

		// Ограничиваем до 20 URL за запрос
		$urls = array_slice($urls, 0, 20);

		$objective = isset($args['objective']) ? sanitize_textarea_field($args['objective']) : '';

		// Получаем API ключ (переиспользуем ту же логику, что и в поиске)
		$integration_settings = get_option('rds_aie_integration_settings', []);
		$api_key = $integration_settings['parallel_api_key'] ?? '';
		
		if (empty($api_key)) {
			$api_key = defined('RDS_AIE_PARALLEL_API_KEY') 
			           ? RDS_AIE_PARALLEL_API_KEY 
			           : apply_filters('rds_aie_parallel_api_key', '');
		}
		
		if (empty($api_key)) {
			return [
				'error' => 'Parallel.ai API key not configured.',
				'hint'  => 'Please add your Parallel.ai API key in RDS AI Engine → Integrations tab.'
			];
		}

		// Кэш на 4 часа (контент страниц меняется реже, чем поисковая выдача)
		$cache_key = 'rds_aie_extract_' . md5(serialize(['urls' => $urls, 'objective' => $objective]));
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		try {
			$request_body = [
				'urls'      => $urls,
				'objective' => !empty($objective) ? $objective : null,
				'advanced_settings' => [
					'full_content' => false
				]
			];

			$response = wp_remote_post('https://api.parallel.ai/v1/extract', [
				'timeout' => 30, // Извлечение может быть тяжелее поиска
				'headers' => [
					'x-api-key'    => $api_key,
					'Content-Type' => 'application/json'
				],
				'body' => wp_json_encode($request_body)
			]);

			if (is_wp_error($response)) {
				throw new Exception($response->get_error_message());
			}

			$body = json_decode(wp_remote_retrieve_body($response), true);
			
			// Проверка ошибок API
			if (isset($body['type']) && $body['type'] === 'error') {
				$error_msg = $body['error']['message'] ?? 'Unknown Parallel.ai error';
				throw new Exception($error_msg);
			}

			if (!isset($body['results']) || !is_array($body['results'])) {
				throw new Exception('Invalid response format from Parallel.ai Extract API');
			}

			// Форматируем результаты
			$formatted = array_map(function($r) {
				$content = implode("\n\n", $r['excerpts'] ?? []);
				return [
					'url'     => $r['url'] ?? '',
					'content' => $content,
					'title'   => $r['title'] ?? ''
				];
			}, $body['results']);

			$result = [
				'urls'       => $urls,
				'objective'  => $objective,
				'results'    => $formatted,
				'total'      => count($formatted),
				'session_id' => $body['session_id'] ?? ''
			];

			set_transient($cache_key, $result, 4 * HOUR_IN_SECONDS);
			return $result;

		} catch (Exception $e) {
			error_log('[RDS AI Engine] Parallel.ai extract error: ' . $e->getMessage());
			return ['error' => 'Content extraction failed: ' . $e->getMessage()];
		}
	}	

	/**
	 * Callback для инструмента загрузки навыка
	 */
	public function tool_read_skill($args) {
		$skill_name = isset($args['skill_name']) ? sanitize_key($args['skill_name']) : '';
		if (empty($skill_name)) {
			return ['error' => 'Skill name is required'];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_skills';
		$skill = $wpdb->get_row($wpdb->prepare(
			"SELECT url FROM {$table_name} WHERE name = %s",
			$skill_name
		));

		if (!$skill) {
			return ['error' => 'Skill not found: ' . $skill_name];
		}

		$content = '';
		$url = $skill->url;

		// Поддержка внешних URL и локальных путей
		if (filter_var($url, FILTER_VALIDATE_URL)) {
			$response = wp_remote_get($url, ['timeout' => 15]);
			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
				$content = wp_remote_retrieve_body($response);
			} else {
				return ['error' => 'Failed to fetch remote skill content.'];
			}
		} else {
			// Локальный путь (абсолютный или относительно WP_CONTENT_DIR)
			$local_path = $url;
			if (!file_exists($local_path)) {
				$local_path = WP_CONTENT_DIR . '/' . ltrim($url, '/');
			}
			
			if (file_exists($local_path) && is_readable($local_path)) {
				$content = file_get_contents($local_path);
			} else {
				return ['error' => 'Local skill file not found or unreadable: ' . $url];
			}
		}

		if (empty($content)) {
			return ['error' => 'Skill content is empty.'];
		}

		return ['success' => true, 'content' => $content];
	}

	/**
	 * Callback для инструмента поиска по базе знаний
	 */
	public function tool_search_knowledge_base($args) {
		$query = isset($args['query']) ? sanitize_text_field($args['query']) : '';
		
		if (empty($query)) {
			return ['error' => 'Search query is required.'];
		}

		try {
			$main = RDS_AIE_Main::get_instance();
			if (!class_exists('RDS_AIE_RAG_Engine')) {
				require_once RDS_AIE_PLUGIN_DIR . 'includes/class-rag-engine.php';
			}
			
			$rag_engine = $main->get_rag_engine();
			$results = $rag_engine->search($query);

			if (empty($results)) {
				return ['status' => 'no_results', 'message' => 'No relevant information found in the Knowledge Base.'];
			}

			// Форматируем результат для ИИ
			$formatted_results = [];
			foreach ($results as $item) {
				$formatted_results[] = [
					'content' => $item['content'],
					'relevance_score' => round($item['score'], 4),
					'title' => $item['title'] ?? 'Unknown Source'
				];
			}

			return [
				'status' => 'success',
				'results_count' => count($formatted_results),
				'chunks' => $formatted_results
			];

		} catch (Exception $e) {
			return ['error' => 'RAG Search failed: ' . $e->getMessage()];
		}
	}
}