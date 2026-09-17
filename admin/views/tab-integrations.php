<?php
/**
 * Integrations Settings Tab
 */
if (!defined('ABSPATH')) exit;

// Обработка сохранения
$message = '';
if (isset($_POST['action']) && $_POST['action'] === 'save_integrations') {
    check_admin_referer('rds_aie_integrations_nonce');
    
    if (current_user_can('manage_options')) {
        $settings = get_option('rds_aie_integration_settings', []);
        
        // Сохраняем Parallel.ai ключ
        if (isset($_POST['parallel_api_key'])) {
            $settings['parallel_api_key'] = sanitize_text_field($_POST['parallel_api_key']);
        }
        
        update_option('rds_aie_integration_settings', $settings);
        $message = '<div class="notice notice-success is-dismissible"><p>' . __('Integration settings saved successfully.', 'rds-ai-engine') . '</p></div>';
    }
}

// Получаем текущие настройки
$settings = get_option('rds_aie_integration_settings', [
    'parallel_api_key' => ''
]);
?>

<div class="wrap rds-aie-integrations">
    <h2><?php _e('External Integrations', 'rds-ai-engine'); ?></h2>
    <?php echo $message; ?>

    <form method="post" action="">
        <?php wp_nonce_field('rds_aie_integrations_nonce'); ?>
        <input type="hidden" name="action" value="save_integrations">

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="parallel_api_key"><?php _e('Parallel.ai API Key', 'rds-ai-engine'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="parallel_api_key" 
                               name="parallel_api_key" 
                               value="<?php echo esc_attr($settings['parallel_api_key']); ?>" 
                               class="regular-text" 
                               autocomplete="off"
                               placeholder="par_...">
                        <p class="description">
                            <?php _e('API key for web search functionality. Get it from your Parallel.ai dashboard.', 'rds-ai-engine'); ?>
                            <br>
                            <a href="https://parallel.ai/dashboard" target="_blank" rel="noopener">
                                <?php _e('Open Parallel.ai Dashboard', 'rds-ai-engine'); ?> 
                            </a>
                        </p>
                    </td>
                </tr>
                
                <!-- Место для будущих интеграций -->
                <?php do_action('rds_aie_integrations_extra_fields', $settings); ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php _e('Save Integrations', 'rds-ai-engine'); ?>
            </button>
        </p>
    </form>
</div>