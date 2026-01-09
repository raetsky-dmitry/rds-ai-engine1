<?php

/**
 * Класс для управления ассистентами
 */

class RDS_AIE_Assistant_Manager
{

	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Получение всех ассистентов
	 */
	public function get_all()
	{
		return $this->db->get_assistants();
	}

	/**
	 * Получение ассистента по ID
	 */
	public function get($id)
	{
		return $this->db->get_assistant($id);
	}

	/**
	 * Сохранение ассистента
	 */
	public function save($data)
	{
		// Валидация данных
		if (empty($data['name'])) {
			throw new Exception(__('Assistant name is required.', 'rds-ai-engine'));
		}

		if (empty($data['system_prompt'])) {
			throw new Exception(__('System prompt is required.', 'rds-ai-engine'));
		}

		return $this->db->save_assistant($data);
	}

	/**
	 * Удаление ассистента
	 */
	public function delete($id)
	{
		return $this->db->delete_assistant($id);
	}

	/**
	 * Получение системного промпта ассистента
	 */
	public function get_system_prompt($assistant_id)
	{
		$assistant = $this->get($assistant_id);
		return $assistant ? $assistant->system_prompt : '';
	}

	/**
	 * Получение параметров ассистента по умолчанию
	 */
	public function get_default_params($assistant_id)
	{
		$assistant = $this->get($assistant_id);

		if (!$assistant) {
			return null;
		}

		return [
			'max_tokens' => $assistant->max_tokens,
			'temperature' => $assistant->temperature,
			'history_enabled' => $assistant->history_enabled,
			'history_messages_count' => $assistant->history_messages_count,
			'knowledge_base_enabled' => $assistant->knowledge_base_enabled,
			'default_model_id' => $assistant->default_model_id
		];
	}
}
