<?php

/**
 * Главный класс плагина RDS AI Engine
 */

class RDS_AIE_Main
{

	/**
	 * Экземпляр класса (Singleton)
	 */
	private static $instance = null;

	/**
	 * Менеджеры компонентов
	 */
	private $model_manager = null;
	private $assistant_manager = null;
	private $history_manager = null;
	private $db = null;
	private $ai_client = null;

	/**
	 * Конструктор (закрытый для Singleton)
	 */
	private function __construct()
	{
		$this->init_hooks();
	}

	/**
	 * Получение экземпляра класса
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Инициализация хуков WordPress
	 */
	private function init_hooks()
	{
		// Инициализация после загрузки всех плагинов
		add_action('plugins_loaded', [$this, 'init_components']);

		// Инициализация админки - позже, чтобы компоненты успели загрузиться
		if (is_admin()) {
			add_action('admin_menu', [$this, 'add_admin_menu'], 20);
			add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 20);
			add_action('wp_ajax_rds_aie_test_chat', [$this, 'ajax_test_chat']);
		}

		// Крон для очистки старой истории
		add_action('rds_aie_daily_cleanup', [$this, 'daily_cleanup']);
		add_action('init', [$this, 'schedule_cleanup']);
	}

	/**
	 * Планирование ежедневной очистки
	 */
	public function schedule_cleanup()
	{
		if (!wp_next_scheduled('rds_aie_daily_cleanup')) {
			wp_schedule_event(time(), 'daily', 'rds_aie_daily_cleanup');
		}
	}

	/**
	 * Ежедневная очистка старой истории
	 */
	public function daily_cleanup()
	{
		$settings = get_option('rds_aie_history_settings', [
			'history_retention_days' => 7,
			'cleanup_enabled' => true
		]);

		if ($settings['cleanup_enabled'] && $settings['history_retention_days'] > 0) {
			$this->get_history_manager()->cleanup_old_history($settings['history_retention_days']);
		}
	}

	/**
	 * Инициализация компонентов
	 */
	public function init_components()
	{
		// Загружаем класс DB если еще не загружен
		if (!class_exists('RDS_AIE_DB')) {
			require_once RDS_AIE_PLUGIN_DIR . 'includes/class-db.php';
		}

		$this->db = new RDS_AIE_DB();
		$this->model_manager = new RDS_AIE_Model_Manager($this->db);
		$this->assistant_manager = new RDS_AIE_Assistant_Manager($this->db);

		// Загружаем класс History Manager если еще не загружен
		if (!class_exists('RDS_AIE_History_Manager')) {
			require_once RDS_AIE_PLUGIN_DIR . 'includes/class-history-manager.php';
		}

		$this->history_manager = new RDS_AIE_History_Manager($this->db);

		// Загружаем AI Client если еще не загружен
		if (!class_exists('RDS_AIE_AI_Client')) {
			require_once RDS_AIE_PLUGIN_DIR . 'includes/class-ai-client.php';
		}

		$this->ai_client = new RDS_AIE_AI_Client(
			$this->model_manager,
			$this->assistant_manager,
			$this->history_manager
		);
	}

	/**
	 * Получить менеджер моделей (с проверкой инициализации)
	 */
	public function get_model_manager()
	{
		if (null === $this->model_manager) {
			$this->init_components();
		}
		return $this->model_manager;
	}

	/**
	 * Получить менеджер ассистентов (с проверкой инициализации)
	 */
	public function get_assistant_manager()
	{
		if (null === $this->assistant_manager) {
			$this->init_components();
		}
		return $this->assistant_manager;
	}

	/**
	 * Получить менеджер истории (с проверкой инициализации)
	 */
	public function get_history_manager()
	{
		if (null === $this->history_manager) {
			$this->init_components();
		}
		return $this->history_manager;
	}

	/**
	 * Получить AI клиент (с проверкой инициализации)
	 */
	public function get_ai_client()
	{
		if (null === $this->ai_client) {
			$this->init_components();
		}
		return $this->ai_client;
	}

	/**
	 * Добавление меню в админку
	 */
	public function add_admin_menu()
	{
		// Главное меню
		add_menu_page(
			__('RDS AI Engine', 'rds-ai-engine'),
			__('RDS AI Engine', 'rds-ai-engine'),
			'edit_posts',
			'rds-aie',
			[$this, 'render_admin_page'],
			'dashicons-robot',
			30
		);

		// Скрываем подменю из списка
		remove_submenu_page('rds-aie', 'rds-aie');
	}

	/**
	 * Рендеринг страницы админки
	 */
	public function render_admin_page()
	{
		// Проверка прав
		if (!current_user_can('edit_posts')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'rds-ai-engine'));
		}

		// Убедимся, что компоненты инициализированы
		$this->get_model_manager();
		$this->get_assistant_manager();

		// Получение текущей вкладки
		$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'models';

		// Подключение шаблона
		include RDS_AIE_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Подключение скриптов и стилей в админке
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Подключаем только на страницах плагина
		if (strpos($hook, 'rds-aie') === false) {
			return;
		}

		// CSS
		wp_enqueue_style(
			'rds-aie-admin',
			RDS_AIE_PLUGIN_URL . 'admin/css/admin.css',
			[],
			RDS_AIE_VERSION
		);

		// JS для тестового чата
		if (isset($_GET['tab']) && $_GET['tab'] === 'chat') {
			wp_enqueue_script(
				'rds-aie-chat',
				RDS_AIE_PLUGIN_URL . 'admin/js/chat.js',
				['jquery'],
				RDS_AIE_VERSION,
				true
			);

			// Локализация для JS
			wp_localize_script('rds-aie-chat', 'rds_aie_ajax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('rds_aie_chat_nonce'),
				'loading_text' => __('Loading...', 'rds-ai-engine'),
				'error_text' => __('An error occurred. Please try again.', 'rds-ai-engine'),
				'select_model_or_assistant' => __('Please select a model or assistant.', 'rds-ai-engine'),
				'confirm_clear' => __('Are you sure you want to clear the chat?', 'rds-ai-engine'),
				'chat_welcome' => __('Start a conversation by typing a message below.', 'rds-ai-engine'),
				'show_debug' => __('Show Debug', 'rds-ai-engine'),
				'hide_debug' => __('Hide Debug', 'rds-ai-engine'),
				'no_request' => __('No request data yet...', 'rds-ai-engine'),
				'no_history' => __('No history data yet...', 'rds-ai-engine'),
				'no_response' => __('No response data yet...', 'rds-ai-engine')
			]);
		}
	}

	/**
	 * AJAX обработчик тестового чата
	 */
	public function ajax_test_chat()
	{
		// Проверка nonce
		check_ajax_referer('rds_aie_chat_nonce', 'nonce');

		// Проверка прав
		if (!current_user_can('edit_posts')) {
			wp_die('Unauthorized', 401);
		}

		// Получение данных
		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
		$model_id = isset($_POST['model_id']) ? intval($_POST['model_id']) : 0;
		$assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;
		$session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

		if (empty($message)) {
			wp_send_json_error(['message' => __('Message is empty.', 'rds-ai-engine')]);
		}

		try {
			// Сначала получаем AI клиент
			$ai_client = $this->get_ai_client();

			// Получаем отладочную информацию ДО отправки запроса
			$debug_info = $this->get_debug_info_for_chat($session_id, $assistant_id, $message, $model_id);

			// Получение ответа от ИИ
			$response = $ai_client->chat_completion([
				'model_id' => $model_id,
				'assistant_id' => $assistant_id,
				'message' => $message,
				'session_id' => $session_id,
				'plugin_id' => 'test_chat'
			]);

			$result = [
				'response' => $response,
				'message' => __('Success', 'rds-ai-engine'),
				'debug' => $debug_info
			];

			wp_send_json_success($result);
		} catch (Exception $e) {
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Получение отладочной информации для чата
	 */
	private function get_debug_info_for_chat($session_id, $assistant_id, $message, $model_id)
	{
		$history_manager = $this->get_history_manager();
		$assistant_manager = $this->get_assistant_manager();
		$model_manager = $this->get_model_manager();

		$assistant = $assistant_manager->get($assistant_id);
		$model = $model_id ? $model_manager->get($model_id) : null;

		// Получаем историю из БД
		$raw_history = $history_manager->get_history($session_id, 20);
		$formatted_history = $history_manager->get_formatted_history($session_id, 20);

		// Получаем, что будет отправлено в ИИ
		$ai_client = $this->get_ai_client();
		$messages_to_send = $ai_client->prepare_messages_debug($assistant_id, $message, $session_id, 'test_chat');

		// Получаем настройки запроса
		$request_params = $ai_client->get_request_params_debug($assistant_id, $model_id);

		// Получаем полный JSON запроса
		$full_request = $ai_client->get_full_request_json_debug($assistant_id, $model_id, $message, $session_id, 'test_chat');

		return [
			'session_id' => $session_id,
			'assistant' => $assistant ? [
				'id' => $assistant->id,
				'name' => $assistant->name,
				'history_enabled' => (bool)$assistant->history_enabled,
				'history_messages_count' => $assistant->history_messages_count,
				'temperature' => $assistant->temperature,
				'max_tokens' => $assistant->max_tokens
			] : null,
			'full_request_json' => $full_request,
			'model' => $model ? [
				'id' => $model->id,
				'name' => $model->name,
				'model_name' => $model->model_name,
				'base_url' => $model->base_url
			] : null,
			'database_history' => [
				'raw_count' => count($raw_history),
				'raw' => $raw_history,
				'formatted_count' => count($formatted_history),
				'formatted' => $formatted_history
			],
			'messages_to_ai' => [
				'count' => count($messages_to_send),
				'messages' => $messages_to_send
			],
			'request_params' => $request_params,
			'timestamp' => current_time('mysql')
		];
	}

	/**
	 * Основной метод для чата (для использования другими плагинами)
	 */
	public function chat_completion($params = [])
	{
		return $this->get_ai_client()->chat_completion($params);
	}

	/**
	 * Активация плагина
	 */
	public static function activate()
	{
		// Загружаем класс DB напрямую
		require_once RDS_AIE_PLUGIN_DIR . 'includes/class-db.php';

		$db = new RDS_AIE_DB();
		$db->create_tables();

		// Добавляем опции по умолчанию
		add_option('rds_aie_version', RDS_AIE_VERSION);
		add_option('rds_aie_default_settings', [
			'max_tokens' => 1000,
			'temperature' => 0.7,
			'history_enabled' => true,
			'history_messages_count' => 10
		]);

		// Настройки истории по умолчанию
		add_option('rds_aie_history_settings', [
			'history_retention_days' => 7,
			'cleanup_enabled' => true
		]);
	}

	/**
	 * Деактивация плагина
	 */
	public static function deactivate()
	{
		// Очистка кеша, остановка крона и т.д.
		wp_clear_scheduled_hook('rds_aie_daily_cleanup');
	}

	/**
	 * Удаление плагина
	 */
	public static function uninstall()
	{
		// Загружаем класс DB напрямую
		require_once RDS_AIE_PLUGIN_DIR . 'includes/class-db.php';

		// Удаление таблиц БД
		$db = new RDS_AIE_DB();
		$db->drop_tables();

		// Удаление опций
		delete_option('rds_aie_version');
		delete_option('rds_aie_default_settings');
		delete_option('rds_aie_history_settings');
	}
}
