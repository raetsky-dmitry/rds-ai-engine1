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

	private function cosine_similarity($vec1, $vec2) {
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
}