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

		// // ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
		// if (defined('WP_DEBUG') && WP_DEBUG) {
		// 	error_log('[RDS AIE Debug agent run] Agent Start. Message: ' . $user_message);
		// 	error_log('=================================================================');
		// }

		$model_manager = new RDS_AIE_Model_Manager($this->db);
		$model = !empty($agent->default_model_id) 
			? $model_manager->get($agent->default_model_id) 
			: $model_manager->get_default_model();

		if (!$model) {
			throw new Exception(__('No model configured for this agent.', 'rds-ai-engine'));
		}

		// Проверка суммаризации перед началом работы
		if (!empty($session_id)) {
			$history_manager = new RDS_AIE_History_Manager($this->db);
			$history_manager->check_and_summarize($session_id, $agent_id);
		}

		// Сохранение запроса пользователя
		if (!empty($session_id)) {
			$this->db->save_conversation_message([
				'session_id' => $session_id,
				'assistant_id' => $agent_id,
				'role' => 'user',
				'content' => $user_message,
				'tokens' => ceil(mb_strlen($user_message, 'UTF-8') / 2),
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
				if (!empty($session_id)) {
					$metadata = [];
					if (isset($response_message['reasoning'])) {
						$metadata['reasoning_content'] = $response_message['reasoning'];
					}
					
					$this->db->save_conversation_message([
						'session_id' => $session_id,
						'assistant_id' => $agent_id,
						'role' => 'assistant',
						'content' => $final_response,
						'metadata' => !empty($metadata) ? $metadata : null,
						'tokens' => ceil(mb_strlen($final_response, 'UTF-8') / 2)
					]);
				}
				break;
			}

			// Добавляем ответ ИИ в локальную историю сообщений
			$messages[] = $response_message;

			// СОХРАНЕНИЕ ШАГА АССИСТЕНТА (с tool_calls)
			if (!empty($session_id)) {
				$metadata = [];


				// // ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
				// if (defined('WP_DEBUG') && WP_DEBUG) {
				// 	error_log('****************************************************************');
				// 	error_log('response_message: ' . json_encode($response_message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				// 	error_log('****************************************************************');
				// }
				
				if (isset($response_message['tool_calls'])) {
					$metadata['tool_calls'] = $response_message['tool_calls'];
					// // ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
					// if (defined('WP_DEBUG') && WP_DEBUG) {
					// 	error_log('-- tool_calls in response_message');
					// }
				}
				if (isset($response_message['reasoning'])) {
					$metadata['reasoning_content'] = $response_message['reasoning'];
					// // ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
					// if (defined('WP_DEBUG') && WP_DEBUG) {
					// 	error_log('-- reasoning_content in response_message');
					// }
				}
				
				$this->db->save_conversation_message([
					'session_id' => $session_id,
					'assistant_id' => $agent_id,
					'role' => 'assistant',
					'content' => $response_message['content'] ?? '',
					'metadata' => !empty($metadata) ? $metadata : null,
					'tokens' => ceil(mb_strlen($response_message['content'], 'UTF-8') / 2)
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
					if (!empty($session_id)) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $skip_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => 0
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
					if (!empty($session_id)) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $result_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => ceil(mb_strlen($result_content, 'UTF-8') / 2)
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
					if (!empty($session_id)) {
						$this->db->save_conversation_message([
							'session_id' => $session_id,
							'assistant_id' => $agent_id,
							'role' => 'tool',
							'content' => $error_content,
							'metadata' => [
								'tool_call_id' => $tool_call['id'],
								'name' => $tool_call['name']
							],
							'tokens' => 0
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
			if (!empty($session_id)) {
				$metadata = [];
				if (isset($final_msg['reasoning'])) {
					$metadata['reasoning_content'] = $final_msg['reasoning'];
				}
				
				$this->db->save_conversation_message([
					'session_id' => $session_id,
					'assistant_id' => $agent_id,
					'role' => 'assistant',
					'content' => $final_response,
					'metadata' => !empty($metadata) ? $metadata : null,
					'tokens' => 0
				]);
			}
		}

		return $final_response;
	}	
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
			'timeout' => 60,
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
		if (!empty($agent->system_prompt)) {
			$messages[] = ['role' => 'system', 'content' => $agent->system_prompt];
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

									//============ ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
									if (defined('WP_DEBUG') && WP_DEBUG) {
										error_log('***** meta:' . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
									}

									if (isset($meta['reasoning_content'])) {

										//============ ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
										if (defined('WP_DEBUG') && WP_DEBUG) {
											error_log('--- reasoning_content:' . $meta['reasoning_content'] . '\n\n');
										}
									
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

			//============ ВРЕМЕННАЯ ОТЛАДКА - удалить после проверки
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('\n**********************************\n messages:\n' . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
			}

		
		return $messages;
	}
}