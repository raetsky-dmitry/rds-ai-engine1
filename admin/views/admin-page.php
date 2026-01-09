<?php

/**
 * Шаблон главной страницы админки плагина
 */

// Получение текущей вкладки
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'models';

// Названия вкладок
$tabs = [
	'models' => __('Models', 'rds-ai-engine'),
	'assistants' => __('Assistants', 'rds-ai-engine'),
	'history' => __('History', 'rds-ai-engine'),
	'chat' => __('Test Chat', 'rds-ai-engine')
];
?>
<div class="wrap rds-aie-admin">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ($tabs as $tab_key => $tab_name): ?>
			<a href="<?php echo admin_url('admin.php?page=rds-aie&tab=' . esc_attr($tab_key)); ?>"
				class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html($tab_name); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tab-content">
		<?php
		// Подключаем контент вкладки
		$tab_file = RDS_AIE_PLUGIN_DIR . 'admin/views/tab-' . $current_tab . '.php';
		if (file_exists($tab_file)) {
			include $tab_file;
		} else {
			echo '<p>' . __('Tab content not found.', 'rds-ai-engine') . '</p>';
		}
		?>
	</div>
</div>