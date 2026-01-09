<?php

/**
 * Класс для управления моделями (креденшиалами)
 */

class RDS_AIE_Model_Manager
{

	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Получение всех моделей
	 */
	public function get_all()
	{
		return $this->db->get_models();
	}

	/**
	 * Получение модели по ID
	 */
	public function get($id)
	{
		$model = $this->db->get_model($id);
		if ($model) {
			// Расшифровываем API ключ
			$model->api_key = $this->decrypt_api_key($model->api_key);
		}
		return $model;
	}

	/**
	 * Сохранение модели
	 */
	public function save($data)
	{
		// Валидация данных
		if (empty($data['name'])) {
			throw new Exception(__('Model name is required.', 'rds-ai-engine'));
		}

		if (empty($data['base_url'])) {
			throw new Exception(__('Base URL is required.', 'rds-ai-engine'));
		}

		if (empty($data['model_name'])) {
			throw new Exception(__('AI model name is required.', 'rds-ai-engine'));
		}

		if (empty($data['api_key'])) {
			throw new Exception(__('API key is required.', 'rds-ai-engine'));
		}

		// Шифрование API ключа
		$data['api_key'] = $this->encrypt_api_key($data['api_key']);

		// Если это модель по умолчанию, снимаем флаг с других
		if (!empty($data['is_default'])) {
			$this->unset_default_models();
		}

		return $this->db->save_model($data);
	}

	/**
	 * Удаление модели
	 */
	public function delete($id)
	{
		// Проверяем, не используется ли модель в ассистентах
		$assistants = $this->db->get_assistants();
		foreach ($assistants as $assistant) {
			if ($assistant->default_model_id == $id) {
				throw new Exception(
					__('Cannot delete model because it is used by one or more assistants.', 'rds-ai-engine')
				);
			}
		}

		return $this->db->delete_model($id);
	}

	/**
	 * Шифрование API ключа
	 */
	private function encrypt_api_key($api_key)
	{
		if (empty($api_key)) {
			return '';
		}

		// Используем встроенные функции WordPress для шифрования
		$key = wp_salt('auth');
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$encrypted = openssl_encrypt($api_key, 'aes-256-cbc', $key, 0, $iv);

		return base64_encode($iv . $encrypted);
	}

	/**
	 * Расшифровка API ключа
	 */
	private function decrypt_api_key($encrypted_key)
	{
		if (empty($encrypted_key)) {
			return '';
		}

		$data = base64_decode($encrypted_key);
		$iv_length = openssl_cipher_iv_length('aes-256-cbc');
		$iv = substr($data, 0, $iv_length);
		$encrypted = substr($data, $iv_length);

		$key = wp_salt('auth');
		return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
	}

	/**
	 * Снятие флага "по умолчанию" со всех моделей
	 */
	private function unset_default_models()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';
		$wpdb->query("UPDATE {$table_name} SET is_default = 0 WHERE is_default = 1");
	}

	/**
	 * Получение модели по умолчанию
	 */
	public function get_default_model()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rds_aie_models';

		$model = $wpdb->get_row("SELECT * FROM {$table_name} WHERE is_default = 1 LIMIT 1");

		if ($model) {
			$model->api_key = $this->decrypt_api_key($model->api_key);
		}

		return $model;
	}
}
