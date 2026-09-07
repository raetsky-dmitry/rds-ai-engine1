<?php
/**
 * Менеджер навыков (Skills) RDS AI Engine
 */
class RDS_AIE_Skill_Manager {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * Регистрация навыка (для использования сторонними плагинами)
	 */
	public function register($data) {
		if (empty($data['name']) || empty($data['url'])) {
			throw new Exception(__('Skill name and URL are required.', 'rds-ai-engine'));
		}
		return $this->db->save_skill($data);
	}

	/**
	 * Получение всех навыков
	 */
	public function get_all() {
		return $this->db->get_skills();
	}

	/**
	 * Получение навыка по ID
	 */
	public function get($id) {
		return $this->db->get_skill($id);
	}

	/**
	 * Удаление навыка
	 */
	public function delete($id) {
		return $this->db->delete_skill($id);
	}

	/**
	 * Привязка навыка к агенту
	 */
	public function assign_to_agent($agent_id, $skill_id) {
		return $this->db->assign_skill_to_agent($agent_id, $skill_id);
	}

	/**
	 * Отвязка навыка от агента
	 */
	public function unassign_from_agent($agent_id, $skill_id) {
		return $this->db->unassign_skill_from_agent($agent_id, $skill_id);
	}

	/**
	 * Получение навыков агента
	 */
	public function get_agent_skills($agent_id) {
		return $this->db->get_agent_skills($agent_id);
	}

	/**
	 * Массовая привязка навыков к агенту
	 * @param int $agent_id
	 * @param array $skill_ids Массив ID навыков
	 */
	public function sync_agent_skills($agent_id, $skill_ids) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_agent_skills';
		
		// Удаляем старые привязки
		$wpdb->delete($table_name, ['agent_id' => intval($agent_id)]);
		
		// Добавляем новые
		if (!empty($skill_ids)) {
			foreach ($skill_ids as $skill_id) {
				$this->assign_to_agent($agent_id, $skill_id);
			}
		}
	}
}