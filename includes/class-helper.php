<?php

/**
 * Вспомогательный класс RDS AI Engine
 */

class RDS_AIE_Helper
{

	/**
	 * Создание nonce для форм
	 */
	public static function create_nonce($action)
	{
		return wp_create_nonce($action);
	}

	/**
	 * Проверка nonce
	 */
	public static function verify_nonce($nonce, $action)
	{
		return wp_verify_nonce($nonce, $action);
	}

	/**
	 * Получение URL страницы плагина
	 */
	public static function get_page_url($tab = '')
	{
		$url = admin_url('admin.php?page=rds-aie');
		if (!empty($tab)) {
			$url .= '&tab=' . urlencode($tab);
		}
		return $url;
	}
}
