<?php
/**
 * Менеджер агентов RDS AI Engine
 */
class RDS_AIE_Agent_Manager {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * Получение всех агентов
	 */
	public function get_all() {
		return $this->db->get_agents();
	}

	/**
	 * Получение агента по ID
	 */
	public function get($id) {
		return $this->db->get_agent($id);
	}

	/**
	 * Сохранение агента
	 */
	public function save($data) {
		if (empty($data['name'])) {
			throw new Exception(__('Agent name is required.', 'rds-ai-engine'));
		}
		if (empty($data['system_prompt'])) {
			throw new Exception(__('System prompt is required.', 'rds-ai-engine'));
		}
		return $this->db->save_agent($data);
	}

	/**
	 * Удаление агента
	 */
	public function delete($id) {
		return $this->db->delete_agent($id);
	}

	/**
	 * Привязка инструмента к агенту
	 */
	public function assign_tool($agent_id, $tool_name, $schema) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_tools';
		
		// Проверяем, не привязан ли уже этот инструмент
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE agent_id = %d AND tool_name = %s",
			$agent_id,
			$tool_name
		));

		if ($exists) {
			// Обновляем схему если нужно
			$wpdb->update($table_name, [
				'tool_schema' => wp_json_encode($schema)
			], ['id' => $exists]);
			return $exists;
		} else {
			// Вставляем новый
			$wpdb->insert($table_name, [
				'agent_id' => $agent_id,
				'tool_name' => $tool_name,
				'tool_schema' => wp_json_encode($schema),
				'is_active' => 1
			]);
			return $wpdb->insert_id;
		}
	}
}