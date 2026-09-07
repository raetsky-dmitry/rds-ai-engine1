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

		// Инструмент чтения поста
		$this->tools['wp_get_post'] = [
			'name' => 'wp_get_post',
			'description' => 'Get the full content of a specific WordPress post by ID.',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [
						'type' => 'integer',
						'description' => 'The ID of the post.'
					]
				],
				'required' => ['post_id']
			],
			'callback' => [$this, 'tool_get_post']
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

	public function tool_get_post($args) {
		$post_id = intval($args['post_id']);
		$post = get_post($post_id);

		if (!$post) {
			return ['error' => 'Post not found'];
		}

		return [
			'id' => $post->ID,
			'title' => $post->post_title,
			'content' => $post->post_content,
			'status' => $post->post_status
		];
	}
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