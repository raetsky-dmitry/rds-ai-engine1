<?php
/**
 * Движок агентов RDS AI Engine
 */
class RDS_AIE_Agent_Engine {
	private $db;
	private $tool_registry;

	public function __construct($db) {
		$this->db = $db;
		$this->tool_registry = RDS_AIE_Tool_Registry::get_instance();
	}

	/**
	 * Запуск агентского цикла
	 */
	public function run($agent_id, $user_message, $session_id = '') {
		$agent = $this->db->get_agent($agent_id);
		if (!$agent) {
			throw new Exception(__('Agent not found.', 'rds-ai-engine'));
		}

		$model_manager = new RDS_AIE_Model_Manager($this->db);
		$model = !empty($agent->default_model_id) 
			? $model_manager->get($agent->default_model_id) 
			: $model_manager->get_default_model();

		if (!$model) {
			throw new Exception(__('No model configured for this agent.', 'rds-ai-engine'));
		}

		// Инициализируем History_Manager один раз — будем использовать для подсчёта токенов
		$history_manager = !empty($session_id) ? new RDS_AIE_History_Manager($this->db) : null;

		// Проверка суммаризации перед началом работы
		if ($history_manager) {
			$history_manager->check_and_summarize($session_id, $agent_id);
		}

		// Сохранение запроса пользователя
		if ($history_manager) {
			$this->db->save_conversation_message([
				'session_id' => $session_id,
				'assistant_id' => $agent_id,
				'role' => 'user',
				'content' => $user_message,
				'tokens' => $history_manager->count_tokens($user_message),
			]);
		}

		$tools_schema = $this->get_tools_schema_for_agent($agent_id);
		$messages = $this->prepare_messages($agent, $user_message, $session_id);
		
		$iteration = 0;
		$max_iterations = intval($agent->max_iterations);
		$total_tool_calls = 0; 
		$final_response = '';

		while ($iteration < $max_iterations) {
			$iteration++;
			
			$response_message = $this->call_llm($model, $messages, $tools_schema, $agent->temperature);
			$tool_calls = $this->parse_tool_calls($response_message);
			
			if (empty($tool_calls)) {
				$final_response = $response_message['content'] ?? '';

				// Сохраняем финальный ответ ассистента (без tool_calls)
				if ($history_manager) {
					$metadata = [];
					$reasoning = $response_message['reasoning_content'] ?? $response_message['reasoning'] ?? null;
					if ($reasoning) {
						$metadata['reasoning_content'] = $reasoning;
					}

					$this->db->save_conversation_message([
						'session_id' => $session_id,
						'assistant_id' => $agent_id,
						'role' => 'assistant',
						'content' => $final_response,
						'metadata' => !empty($metadata) ? $metadata : null,
						'tokens' => $history_manager->count_tokens($final_response)
					]);
				}
				break;
			}

			// Добавляем ответ ИИ в локальную историю сообщений
			$messages[] = $response_message;

			// СОХРАНЕНИЕ ШАГА АССИСТЕНТА (с tool_calls)
			if ($history_manager) {
				$metadata = [];
				if (isset($response_message['tool_calls'])) {
					$metadata['tool_calls'] = $response_message['tool_calls'];
				}
				$reasoning = $response_message['reasoning_content'] ?? $response_message['reasoning'] ?? null;
				if ($reasoning) {
					$metadata['reasoning_content'] = $reasoning;
				}

				$this->db->save_conversation_message([
					'session_id' => $session_id,
					'assistant_id' => $agent_id,
					'role' => 'assistant',
					'content' => $response_message['content'] ?? '',
					'metadata' => !empty($metadata) ? $metadata : null,
					'tokens' => $history_manager->count_tokens($response_message['content'] ?? '')
				]);
			}

			// Выполняем каждый вызванный инструмент
			foreach ($tool_calls as $tool_call) {
				// Проверяем лимит ПЕРЕД выполнением каждого инструмента
				if ($total_tool_calls >= $max_iterations) {
					$skip_content = json_encode([
						'status' => 'skipped',
						'reason' => 'You have reached the maximum number of tool calls. Please provide a final summary based on the information you have gathered so far.'
					], JSON_UNESCAPED_UNICODE);

					$messages[] = [
						'role' => 'tool',
						'tool_call_id' => $tool_call['id'],
						'name' => $tool_call['name'],
						'content' => $skip_content
					];

					// Сохраняем факт пропуска инструмента в БД
					if ($history_manager) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $skip_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => $history_manager->count_tokens($skip_content)
						]);
					}
					continue;
				}

				$total_tool_calls++;
				
				try {
					$result = $this->tool_registry->execute_tool($tool_call['name'], $tool_call['arguments']);
					$result_content = is_string($result) ? $result : wp_json_encode($result, JSON_UNESCAPED_UNICODE);
					
					$messages[] = [
						'role' => 'tool',
						'tool_call_id' => $tool_call['id'],
						'name' => $tool_call['name'],
						'content' => $result_content
					];

					// СОХРАНЕНИЕ РЕЗУЛЬТАТА ИНСТРУМЕНТА
					if ($history_manager) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $result_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => $history_manager->count_tokens($result_content)
						]);
					}
				} catch (Exception $e) {
					$error_content = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
					
					$messages[] = [
						'role' => 'tool',
						'tool_call_id' => $tool_call['id'],
						'name' => $tool_call['name'],
						'content' => $error_content
					];

					// СОХРАНЕНИЕ ОШИБКИ ИНСТРУМЕНТА
					if ($history_manager) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $error_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => $history_manager->count_tokens($error_content)
						]);
					}
				}
			}
		}

		// Если цикл завершился, а финального текстового ответа от ИИ так и не поступило
		if (empty($final_response)) {
			$messages[] = [
				'role' => 'system', 
				'content' => 'You have reached the maximum number of tool calls. Please provide a final summary based on the information you have gathered so far.'
			];
			
			// Делаем один финальный "тихий" вызов без инструментов, чтобы получить текст
			$final_msg = $this->call_llm($model, $messages, [], $agent->temperature);
			$final_response = $final_msg['content'] ?? __('I have reached the maximum number of tool calls.', 'rds-ai-engine');

			// Сохраняем принудительный финальный ответ
			if ($history_manager) {
				$metadata = [];
				$reasoning = $final_msg['reasoning_content'] ?? $final_msg['reasoning'] ?? null;
				if ($reasoning) {
					$metadata['reasoning_content'] = $reasoning;
				}

				$this->db->save_conversation_message([
					'session_id' => $session_id,
					'assistant_id' => $agent_id,
					'role' => 'assistant',
					'content' => $final_response,
					'metadata' => !empty($metadata) ? $metadata : null,
					'tokens' => $history_manager->count_tokens($final_response)
				]);
			}
		}

		return $final_response;
	}

	// /**
	//  * Установить безопасные лимиты времени для длительных операций агента.
	//  * Вызывать ТОЛЬКО внутри обработчика запроса, перед запуском run().
	//  */
	// public static function set_safe_limits() {
	// 	// Увеличиваем лимит выполнения скрипта
	// 	@set_time_limit(300);
		
	// 	// Увеличиваем таймаут сокетов для wp_remote_post/get
	// 	@ini_set('default_socket_timeout', 300);
		
	// 	// Увеличиваем лимит памяти (агенты с инструментами потребляют много)
	// 	@ini_set('memory_limit', '512M');
	// }
	
	private function call_llm($model, $messages, $tools, $temperature) {
		$url = trailingslashit($model->base_url) . 'chat/completions';
		
		$body = [
			'model' => $model->model_name,
			'messages' => $messages,
			'temperature' => (float)$temperature,
		];

		if (!empty($tools)) {
			$body['tools'] = $tools;
			$body['tool_choice'] = 'auto';
		}

		$args = [
			'timeout' => 300,
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

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		if (isset($data['error'])) {
			throw new Exception($data['error']['message']);
		}

		return $data['choices'][0]['message'];
	}

	private function parse_tool_calls($message) {
		if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
			$calls = [];
			foreach ($message['tool_calls'] as $call) {
				if ($call['type'] === 'function') {
					$calls[] = [
						'id' => $call['id'],
						'name' => $call['function']['name'],
						'arguments' => json_decode($call['function']['arguments'], true)
					];
				}
			}
			return $calls;
		}
		return [];
	}

	private function get_tools_schema_for_agent($agent_id) {
		$raw_tools = $this->db->get_agent_tools($agent_id);
		$formatted_tools = [];
		
		foreach ($raw_tools as $tool) {
			$schema = json_decode($tool->tool_schema, true);
			if ($schema) {
				$formatted_tools[] = [
					'type' => 'function',
					'function' => [
						'name' => $schema['name'] ?? $tool->tool_name,
						'description' => $schema['description'] ?? '',
						'parameters' => $schema
					]
				];
			}
		}

		// АВТОМАТИЧЕСКИ добавляем read_skill, если у агента есть навыки
		$agent_skills = $this->db->get_agent_skills($agent_id);
		if (!empty($agent_skills)) {
			$registry = RDS_AIE_Tool_Registry::get_instance();
			$all_tools = $registry->get_all_tools();
			
			if (isset($all_tools['read_skill'])) {
				$skill_tool = $all_tools['read_skill'];
				$formatted_tools[] = [
					'type' => 'function',
					'function' => [
						'name' => $skill_tool['name'],
						'description' => $skill_tool['description'],
						'parameters' => $skill_tool['schema']
					]
				];
			}
		}

		// АВТОМАТИЧЕСКИ добавляем search_knowledge_base, если база знаний не пуста
		$kb_chunks = $this->db->get_all_knowledge_chunks(); // Можно заменить на более легкий запрос COUNT(*)
		if (!empty($kb_chunks)) {
			$registry = RDS_AIE_Tool_Registry::get_instance();
			$all_tools = $registry->get_all_tools();
			
			if (isset($all_tools['search_knowledge_base'])) {
				$rag_tool = $all_tools['search_knowledge_base'];
				$formatted_tools[] = [
					'type' => 'function',
					'function' => [
						'name' => $rag_tool['name'],
						'description' => $rag_tool['description'],
						'parameters' => $rag_tool['schema']
					]
				];
			}
		}

		return $formatted_tools;
	}
		
	private function prepare_messages($agent, $user_message, $session_id) {
		$messages = [];
		
		// 1. Добавляем основной системный промпт агента
		// 1.1. Основной системный промпт
		$system_prompt = $agent->system_prompt ?? '';

		// 1.2. Добавляем информацию о доступных навыках
		$agent_skills = $this->db->get_agent_skills($agent->id);
		if (!empty($agent_skills)) {
			$skills_info = "\n\n## Available Skills\nYou have access to the following skills. Use the 'read_skill' tool to load their full instructions when needed:\n";
			foreach ($agent_skills as $skill) {
				$skills_info .= "- **{$skill->name}**: {$skill->description}\n";
			}
			$system_prompt .= $skills_info;
		}

		if (!empty($system_prompt)) {
			$messages[] = ['role' => 'system', 'content' => $system_prompt];
		}
		
		// 2. Получаем историю из БД
		if (!empty($session_id)) {
			$history_manager = new RDS_AIE_History_Manager($this->db);
			// Берем с запасом, чтобы точно захватить резюме, если оно есть
			$raw_history = $history_manager->get_history($session_id, 100); 

			$summary_message = null;
			$other_messages = [];

			// Разделяем историю на резюме и обычные сообщения
			foreach ($raw_history as $msg) {
				// Ищем наше специальное сообщение с резюме
				if ($msg->role === 'system' && strpos($msg->content, '[Conversation Summary]:') !== false) {
					$summary_message = [
						'role' => 'system',
						'content' => $msg->content
					];
				} else {
					// Все остальные сообщения (user/assistant/tool) добавляем в общий список
					// Пропускаем дублирование текущего сообщения пользователя, если оно уже сохранено
					if (!($msg->role === 'user' && $msg->content === $user_message)) {
						$other_message = [
							'role' => $msg->role,
							'content' => $msg->content
						];

						// Добавляем метаданные (tool_calls, tool_call_id, reasoning_content)
						if (!empty($msg->metadata)) {
							$meta = json_decode($msg->metadata, true);
							if (is_array($meta)) {
								// Для assistant: добавляем tool_calls и reasoning_content
								if ($msg->role === 'assistant') {
									if (isset($meta['tool_calls']) && is_array($meta['tool_calls'])) {
										$other_message['tool_calls'] = $meta['tool_calls'];
										// Если есть tool_calls, content может быть null или пустым
										if (empty($other_message['content'])) {
											$other_message['content'] = null;
										}
									}
									if (isset($meta['reasoning_content'])) {
										$other_message['reasoning_content'] = $meta['reasoning_content'];
									}
								}

								// Для tool: добавляем tool_call_id и name
								if ($msg->role === 'tool') {
									if (isset($meta['tool_call_id'])) {
										$other_message['tool_call_id'] = $meta['tool_call_id'];
									}
									if (isset($meta['name'])) {
										$other_message['name'] = $meta['name'];
									}
								}
							}
						}

						// Добавляем сообщение в массив
						$other_messages[] = $other_message;
					}
				}
			}

			// 3. Вставляем резюме СРАЗУ ПОСЛЕ основного системного промпта
			if ($summary_message) {
				$messages[] = $summary_message;
			}

			// 4. Добавляем остальные сообщения истории
			$messages = array_merge($messages, $other_messages);
		}

		// 5. Добавляем текущее сообщение пользователя в самый конец
		$messages[] = ['role' => 'user', 'content' => $user_message];
		
		return $messages;
	}
}