<?php

/**
 * Фабрика генераторов
 */

class RDS_AIE_Generator_Factory
{
	private $db;
	private $model_manager;

	public function __construct($db, $model_manager)
	{
		$this->db = $db;
		$this->model_manager = $model_manager;
	}

	/**
	 * Создание генератора по типу
	 */
	public function create_generator($model_id, $params = [])
	{
		$model = $this->model_manager->get($model_id);

		if (!$model) {
			throw new Exception(__('Model not found', 'rds-ai-engine'));
		}

		// Определяем тип генератора
		$type = $this->determine_generator_type($model, $params);

		switch ($type) {
			case 'image':
				return new RDS_AIE_Image_Generator($model, $params, $this->db);
			case 'text':
			default:
				return new RDS_AIE_Text_Generator($model, $params, $this->db);
		}
	}

	/**
	 * Определение типа генератора
	 */
	private function determine_generator_type($model, $params)
	{
		// Если тип указан явно в параметрах
		if (!empty($params['type'])) {
			return $params['type'];
		}

		// Если тип указан в модели
		if (!empty($model->model_type) && $model->model_type !== 'both') {
			return $model->model_type;
		}

		// Если в параметрах есть ключевые слова для изображений
		if (isset($params['prompt']) || isset($params['size']) || isset($params['response_format'])) {
			return 'image';
		}

		// По умолчанию - текстовый
		return 'text';
	}

	/**
	 * Получение генератора для изображений
	 */
	public function create_image_generator($model_id = 0, $params = [])
	{
		// Если модель не указана, используем модель по умолчанию для изображений
		if ($model_id === 0) {
			$model = $this->model_manager->get_default_model_by_type('image');
			if (!$model) {
				throw new Exception(__('No default image model configured', 'rds-ai-engine'));
			}
			$model_id = $model->id;
		}

		$params['type'] = 'image';
		return $this->create_generator($model_id, $params);
	}

	/**
	 * Получение генератора для текста
	 */
	public function create_text_generator($model_id = 0, $params = [])
	{
		// Если модель не указана, используем модель по умолчанию для текста
		if ($model_id === 0) {
			$model = $this->model_manager->get_default_model_by_type('text');
			if (!$model) {
				throw new Exception(__('No default text model configured', 'rds-ai-engine'));
			}
			$model_id = $model->id;
		}

		$params['type'] = 'text';
		return $this->create_generator($model_id, $params);
	}
}
