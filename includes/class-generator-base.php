<?php

abstract class RDS_AIE_Generator_Base
{
	protected $model;
	protected $params;
	protected $db;

	public function __construct($model, $params, $db)
	{
		$this->model = $model;
		$this->params = $params;
		$this->db = $db;
	}

	/**
	 * Получение параметров
	 */
	public function get_params()
	{
		return $this->params;
	}

	/**
	 * Получение модели
	 */
	public function get_model()
	{
		return $this->model;
	}

	/**
	 * Валидация параметров
	 */
	abstract public function validate_params($params);

	/**
	 * Подготовка запроса к API
	 */
	abstract public function prepare_request($input);

	/**
	 * Обработка ответа от API
	 */
	abstract public function process_response($response);

	/**
	 * Сохранение результата в БД
	 */
	public function save_result($data)
	{
		$defaults = [
			'model_id' => $this->model ? $this->model->id : 0,
			'type' => $this->get_type(),
			'prompt' => '',
			'parameters' => $this->params,
			'response_data' => '',
			'response_format' => 'text',
			'tokens_used' => 0,
			'status' => 'pending',
			'created_at' => current_time('mysql')
		];

		$data = wp_parse_args($data, $defaults);
		return $this->db->save_generation($data);
	}

	/**
	 * Получение типа генератора
	 */
	abstract protected function get_type();

	/**
	 * Логирование ошибки (публичный метод)
	 */
	public function log_error($generation_id, $error_message)
	{
		return $this->db->save_generation([
			'id' => $generation_id,
			'status' => 'error',
			'error_message' => $error_message
		]);
	}

	/**
	 * Логирование успеха (публичный метод)
	 */
	public function log_success($generation_id, $response_data, $tokens_used = 0)
	{
		return $this->db->save_generation([
			'id' => $generation_id,
			'status' => 'success',
			'response_data' => $response_data,
			'tokens_used' => $tokens_used
		]);
	}
}
