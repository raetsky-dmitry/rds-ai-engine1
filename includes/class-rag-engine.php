<?php
/**
 * Движок RAG (Retrieval-Augmented Generation) для RDS AI Engine
 */
class RDS_AIE_RAG_Engine {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * Разбиение текста на чанки с учетом перекрытия (overlap)
	 */
	public function chunk_text($text, $chunk_size = 500, $overlap = 50) {
		$chunks = [];
		$length = mb_strlen($text, 'UTF-8');
		
		if ($length <= 0) return [];
		if ($length <= $chunk_size) {
			return [$text];
		}

		// Защита от некорректных настроек
		if ($overlap >= $chunk_size) {
			$overlap = max(0, $chunk_size - 1);
		}

		$start = 0;
		$step = $chunk_size - $overlap; // Минимальный шаг вперед

		while ($start < $length) {
			$end = min($start + $chunk_size, $length);
			
			// Если мы не в самом конце текста, пытаемся разбить по пробелу
			if ($end < $length) {
				$segment = mb_substr($text, $start, $chunk_size, 'UTF-8');
				$last_space = mb_strrpos($segment, ' ', 0, 'UTF-8');
				
				// Если нашли пробел и он не слишком близко к началу (чтобы не делать микро-чанки)
				if ($last_space !== false && $last_space > ($chunk_size * 0.2)) {
					$end = $start + $last_space;
				}
			}

			$chunk = trim(mb_substr($text, $start, $end - $start, 'UTF-8'));
			if (!empty($chunk)) {
				$chunks[] = $chunk;
			}
			
			// Сдвигаем курсор
			$start += $step;
			
			// Если из-за разбиения по пробелам мы сдвинулись меньше чем на step, 
			// или если мы уже в конце, корректируем start
			if ($start < $end) {
				$start = $end;
			}
			
			// Если start перевалил за длину, выходим
			if ($start >= $length) break;
		}
		
		return $chunks;
	}

	/**
	 * Генерация эмбеддинга через API выбранной модели
	 */
	public function generate_embedding($text, $model_id) {
		$model_manager = new RDS_AIE_Model_Manager($this->db);
		$model = $model_manager->get($model_id);
		
		if (!$model) {
			throw new Exception(__('Embedding model not found.', 'rds-ai-engine'));
		}

		$url = trailingslashit($model->base_url) . 'embeddings';
		$body = [
			'model' => $model->model_name,
			'input' => $text
		];

		$args = [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $model->api_key
			],
			'body' => wp_json_encode($body)
		];

		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			throw new Exception($response->get_error_message());
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);
		
		if (isset($data['error'])) {
			throw new Exception($data['error']['message']);
		}

		return $data['data'][0]['embedding'] ?? null;
	}

	/**
	 * Индексация текста (разбиение + сохранение в БД)
	 * @return string|false document_id при успехе или false при ошибке
	 */
	public function index_text($text, $source_name = 'manual', $title = '', $document_id = '') {
		$settings = get_option('rds_aie_rag_settings', []);
		$model_id = $settings['embedding_model_id'] ?? 0;
		$chunk_size = intval($settings['chunk_size'] ?? 500);
		$overlap = intval($settings['overlap'] ?? 50);

		if (empty($model_id)) {
			throw new Exception(__('Please select an embedding model in RAG Settings.', 'rds-ai-engine'));
		}

		// Генерируем ID документа, если не передан явно
		if (empty($document_id)) {
			$document_id = md5($source_name . microtime(true) . wp_rand());
		}

		$chunks = $this->chunk_text($text, $chunk_size, $overlap);

    
        // // Временная диагностика: останавливаем выполнение и показываем результат
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     echo "Chunks count: " . count($chunks) . "<br>";
        //     echo "Chunk size setting: {$chunk_size}<br>";
        //     echo "Overlap setting: {$overlap}<br><br><br>";
        //     foreach ($chunks as $i => $c) {
        //         echo "--- Chunk {$i} (len: " . mb_strlen($c) . ") ---<br>";
        //         echo mb_substr($c, 0, 100) . "...<br><br>";
        //     }
        //     die(); // Останавливаем здесь
        // }
    
        
		$count = 0;

		foreach ($chunks as $index => $chunk) {
			if (mb_strlen($chunk, 'UTF-8') < 10) continue;

			try {
				$embedding = $this->generate_embedding($chunk, $model_id);
				if ($embedding) {
					$this->db->save_knowledge_chunk([
						'document_id' => $document_id,
						'title' => $title ? $title . ' (Part ' . ($index + 1) . ')' : '',
						'content' => $chunk,
						'embedding' => $embedding,
						'source_name' => $source_name
					]);
					$count++;
				}
			} catch (Exception $e) {
				error_log('RDS AI Engine RAG Indexing Error: ' . $e->getMessage());
			}
		}

		return $count > 0 ? $document_id : false;
	}

	/**
	 * Обновление документа: удаление старых чанков и индексация новых
	 */
	public function update_document($document_id, $new_text, $source_name = '', $title = '') {
		// Удаляем старые чанки
		$this->db->delete_knowledge_document($document_id);
		
		// Индексируем заново с тем же ID
		return $this->index_text($new_text, $source_name, $title, $document_id);
	}

	/**
	 * Поиск похожих чанков
	 */
	public function search($query, $limit = 3) {
		$settings = get_option('rds_aie_rag_settings', []);
		$model_id = $settings['embedding_model_id'] ?? 0;
		$expected_dims = intval($settings['embedding_dims'] ?? 1536); // <-- Берем из настроек RAG
		$hybrid_search = !empty($settings['hybrid_search']);
		$search_limit = intval($settings['search_results_count'] ?? $limit);

		if (empty($model_id)) return [];

		$query_vector = $this->generate_embedding($query, $model_id);
		if (!$query_vector) return [];

		$chunks = $this->db->get_all_knowledge_chunks(); 
		$scores = [];

		foreach ($chunks as $chunk) {
			$vector = json_decode($chunk->embedding, true);
			
			// Проверяем совпадение размерности перед вычислениями
			if (is_array($vector) && count($vector) === $expected_dims) {
				$similarity = $this->cosine_similarity($query_vector, $vector);
				
				$keyword_score = 0;
				if ($hybrid_search) {
					$common_words = count(array_intersect(
						explode(' ', mb_strtolower($query)), 
						explode(' ', mb_strtolower($chunk->content))
					));
					$keyword_score = $common_words / max(count(explode(' ', $query)), 1);
				}

				$scores[] = [
					'id' => $chunk->id,
					'content' => $chunk->content,
					'title' => $chunk->title,
					'score' => $similarity + ($keyword_score * 0.1)
				];
			}
		}

		usort($scores, function($a, $b) {
			return $b['score'] <=> $a['score'];
		});

		return array_slice($scores, 0, $search_limit);
	}

	public function cosine_similarity($vec1, $vec2) {
		$dot_product = 0;
		$norm1 = 0;
		$norm2 = 0;
		
		for ($i = 0; $i < count($vec1); $i++) {
			$dot_product += $vec1[$i] * $vec2[$i];
			$norm1 += $vec1[$i] * $vec1[$i];
			$norm2 += $vec2[$i] * $vec2[$i];
		}
		
		if ($norm1 == 0 || $norm2 == 0) return 0;
		return $dot_product / (sqrt($norm1) * sqrt($norm2));
	}

	/**
	 * Публичный метод для внешних плагинов
	 */
	public function add_chunks_from_external($text, $source_name, $title = '') {
		return $this->index_text($text, $source_name, $title);
	}

	// ========= Работа с постами ==============

    /**
     * Генерация краткого резюме (summary) для поста с помощью LLM
     */
    public function generate_post_summary($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        // Берем заголовок и первые 2000 символов контента для контекста
        $context = $post->post_title . "\n\n" . wp_trim_words($post->post_content, 400);
        
        $model_manager = new RDS_AIE_Model_Manager($this->db);
        // Используем модель по умолчанию или ту, что настроена для RAG
        $settings = get_option('rds_aie_rag_settings', []);
        $model_id = $settings['embedding_model_id'] ?? 0;
        
        // Если для эмбеддинга выбрана специальная модель, попробуем взять текстовую по умолчанию для саммаризации
        $model = $model_manager->get_default_model(); 
        if (!$model) return '';

        $url = trailingslashit($model->base_url) . 'chat/completions';
        $body = [
            'model' => $model->model_name,
            'messages' => [
                ['role' => 'system', 'content' => 'Summarize the following text in 2-3 sentences. Focus on the main topic and key facts. IMPORTANT: You MUST write the summary in the SAME LANGUAGE as the source text.'],
                ['role' => 'user', 'content' => $context]
            ],
            'temperature' => 0.3
        ];

        $args = [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $model->api_key
            ],
            'body' => wp_json_encode($body)
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) return '';
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Индексация поста: генерация саммари и сохранение эмбединга
     */
    public function index_post($post_id) {
        $post = get_post($post_id);
        if (!$post) return false;

        $settings = get_option('rds_aie_rag_settings', []);
        $model_id = $settings['embedding_model_id'] ?? 0;
        if (empty($model_id)) throw new Exception(__('No embedding model configured.', 'rds-ai-engine'));

        $model_manager = new RDS_AIE_Model_Manager($this->db);
        $model = $model_manager->get($model_id);
        if (!$model) throw new Exception(__('Embedding model not found.', 'rds-ai-engine'));

        // 1. Генерируем саммари
        $summary = $this->generate_post_summary($post_id);
        if (empty($summary)) $summary = $post->post_title; // Fallback если ИИ не ответил

        // 2. Генерируем эмбединг саммари
        $embedding = $this->generate_embedding($summary, $model_id);
        if (!$embedding) return false;

        // 3. Сохраняем в нашу новую таблицу
        global $wpdb;
        $table_name = $wpdb->prefix . 'rds_aie_embeddings';
        
        $wpdb->replace($table_name, [
            'post_id'    => $post_id,
            'post_type'  => $post->post_type,
            'summary'    => $summary,
            'embedding'  => wp_json_encode($embedding),
            'model_name' => $model->model_name
        ]);

        return true;
    }

    /**
     * Векторный поиск по постам
     */
    public function vector_search_posts($query, $post_type = '', $limit = 5, $threshold = 0.4) {
        $settings = get_option('rds_aie_rag_settings', []);
        $model_id = $settings['embedding_model_id'] ?? 0;
        if (empty($model_id)) return [];

        // Генерируем вектор для запроса
        $query_vector = $this->generate_embedding($query, $model_id);
        if (!$query_vector) return [];

        global $wpdb;
        $table_name = $wpdb->prefix . 'rds_aie_embeddings';
        
        // Получаем все эмбединги нужного типа (в будущем можно оптимизировать через SQL-поиск, если БД поддерживает векторы)
        $where = "model_name = %s";
        $params = [$wpdb->get_var($wpdb->prepare("SELECT model_name FROM {$wpdb->prefix}rds_aie_models WHERE id = %d", $model_id))];
        
        if (!empty($post_type)) {
            $where .= " AND post_type = %s";
            $params[] = $post_type;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, summary, embedding FROM {$table_name} WHERE {$where}",
            $params
        ));

        $scores = [];
        foreach ($rows as $row) {
            $vector = json_decode($row->embedding, true);
            if (is_array($vector) && count($vector) === count($query_vector)) {
                $similarity = $this->cosine_similarity($query_vector, $vector);
                if ($similarity >= $threshold) {
                    $scores[] = [
                        'post_id' => $row->post_id,
                        'summary' => $row->summary,
                        'score'   => $similarity
                    ];
                }
            }
        }

        // Сортируем по убыванию сходства
        usort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scores, 0, $limit);
    }
}