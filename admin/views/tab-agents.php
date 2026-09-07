<?php
/**
 * Вкладка управления агентами
 */
$main = RDS_AIE_Main::get_instance();
$model_manager = $main->get_model_manager();
$agent_manager = $main->get_agent_manager();
$tool_registry = RDS_AIE_Tool_Registry::get_instance();

// Получаем текстовые модели для выбора
$text_models = $model_manager->get_models_by_type('text');
$both_models = $model_manager->get_models_by_type('both');
$models = array_merge($text_models, $both_models);

$agents = $agent_manager->get_all();
$all_tools = $tool_registry->get_all_tools();

// Данные для редактирования (определяем ДО обработки формы)
$edit_agent = null;
if (isset($_GET['edit'])) {
    $edit_agent = $agent_manager->get(intval($_GET['edit']));
}

// Инициализируем Skill Manager если еще не создан
if (!class_exists('RDS_AIE_Skill_Manager')) {
    require_once RDS_AIE_PLUGIN_DIR . 'includes/class-skill-manager.php';
}
$skill_manager = new RDS_AIE_Skill_Manager($main->get_db());

$message = ''; // Переменная для сообщений

// Обработка действий
if (isset($_POST['action'])) {
    $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
    if (wp_verify_nonce($nonce, 'rds_aie_agents')) {
        try {
            switch ($_POST['action']) {
                case 'save_agent':
                    $agent_data = [
                        'id' => isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0,
                        'name' => sanitize_text_field($_POST['name']),
                        'system_prompt' => sanitize_textarea_field($_POST['system_prompt']),
                        'default_model_id' => !empty($_POST['default_model_id']) ? intval($_POST['default_model_id']) : null,
                        'max_iterations' => intval($_POST['max_iterations']),
                        'temperature' => floatval($_POST['temperature']),
                        // Новые поля суммаризации
                        'summary_enabled' => isset($_POST['summary_enabled']) ? 1 : 0,
                        'max_history_size' => intval($_POST['max_history_size'] ?? 4000),
                        'min_last_messages' => intval($_POST['min_last_messages'] ?? 3),
                        'summary_model_id' => !empty($_POST['summary_model_id']) ? intval($_POST['summary_model_id']) : null
                    ];
                    
                    $saved_id = $agent_manager->save($agent_data);

                    // Привязка инструментов
                    if ($saved_id && isset($_POST['tools'])) {
                        foreach ($_POST['tools'] as $tool_name) {
                            if (isset($all_tools[$tool_name])) {
                                $agent_manager->assign_tool($saved_id, $tool_name, $all_tools[$tool_name]['schema']);
                            }
                        }
                    }


                    // Синхронизация навыков
                    if (isset($skill_manager) && isset($_POST['skill_ids'])) {
                        $skill_manager->sync_agent_skills($saved_id, $_POST['skill_ids']);
                    } elseif (isset($skill_manager)) {
                        // Если чекбоксы не присланы, значит все отвязываем
                        $skill_manager->sync_agent_skills($saved_id, []);
                    }

                    $message = '<div class="notice notice-success"><p>' . __('Agent saved successfully.', 'rds-ai-engine') . '</p></div>';
                    
                    // ВАЖНО: Обновляем объект edit_agent свежими данными из БД, чтобы форма не "слетала"
                    if ($saved_id) {
                        $edit_agent = $agent_manager->get($saved_id);
                    }
                    
                    // Обновляем общий список
                    $agents = $agent_manager->get_all();
                    break;
                
                case 'delete_agent':
                    $agent_id = intval($_POST['agent_id']);
                    $agent_manager->delete($agent_id);
                    $message = '<div class="notice notice-success"><p>' . __('Agent deleted successfully.', 'rds-ai-engine') . '</p></div>';
                    $agents = $agent_manager->get_all();
                    // При удалении сбрасываем режим редактирования, если удаляли текущего
                    if ($edit_agent && $edit_agent->id == $agent_id) {
                        $edit_agent = null;
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}
?>

<div class="wrap rds-aie-agents">
    <h1><?php _e('AI Agents', 'rds-ai-engine'); ?></h1>

    <?php echo $message; ?>

    <!-- Форма добавления/редактирования -->
    <div class="agent-form">
        <h2><?php echo $edit_agent ? __('Edit Agent', 'rds-ai-engine') : __('Add New Agent', 'rds-ai-engine'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('rds_aie_agents'); ?>
            <input type="hidden" name="action" value="save_agent">
            <input type="hidden" name="agent_id" value="<?php echo $edit_agent ? esc_attr($edit_agent->id) : ''; ?>">
            
            <table class="form-table">
                <tr>
                    <th><label for="name"><?php _e('Name', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="text" id="name" name="name" value="<?php echo $edit_agent ? esc_attr($edit_agent->name) : ''; ?>" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="system_prompt"><?php _e('System Prompt', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <textarea id="system_prompt" name="system_prompt" rows="5" class="large-text" required><?php echo $edit_agent ? esc_textarea($edit_agent->system_prompt) : ''; ?></textarea>
                        <p class="description"><?php _e('Instructions for the agent behavior and role.', 'rds-ai-engine'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_model_id"><?php _e('Default Model', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <select id="default_model_id" name="default_model_id" class="regular-text">
                            <option value=""><?php _e('-- Select Model --', 'rds-ai-engine'); ?></option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo esc_attr($model->id); ?>" <?php selected($edit_agent ? $edit_agent->default_model_id : '', $model->id); ?>>
                                    <?php echo esc_html($model->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="max_iterations"><?php _e('Max Tool Calls', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="number" id="max_iterations" name="max_iterations" min="1" max="10" step="1" value="<?php echo $edit_agent ? esc_attr($edit_agent->max_iterations) : 3; ?>">
                        <p class="description"><?php _e('Maximum number of tool calls per request to prevent infinite loops.', 'rds-ai-engine'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="temperature"><?php _e('Temperature', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="number" id="temperature" name="temperature" min="0" max="2" step="0.1" value="<?php echo $edit_agent ? esc_attr($edit_agent->temperature) : 0.7; ?>">
                    </td>
                </tr>
                
                <!-- Поля суммаризации -->
                <tr>
                    <th><label for="summary_enabled"><?php _e('Enable History Summarization', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="summary_enabled" name="summary_enabled" value="1" <?php checked($edit_agent ? $edit_agent->summary_enabled : 0); ?>>
                            <?php _e('Automatically summarize old conversation history to save tokens.', 'rds-ai-engine'); ?>
                        </label>
                    </td>
                </tr>
                <tr class="summary-settings" style="<?php echo ($edit_agent && $edit_agent->summary_enabled) ? '' : 'display:none;'; ?>">
                    <th><label for="max_history_size"><?php _e('Max History Size (chars)', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="number" id="max_history_size" name="max_history_size" min="500" step="100" value="<?php echo $edit_agent ? esc_attr($edit_agent->max_history_size ?? 4000) : 4000; ?>">
                    </td>
                </tr>
                <tr class="summary-settings" style="<?php echo ($edit_agent && $edit_agent->summary_enabled) ? '' : 'display:none;'; ?>">
                    <th><label for="min_last_messages"><?php _e('Keep Last Messages', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="number" id="min_last_messages" name="min_last_messages" min="1" max="10" step="1" value="<?php echo $edit_agent ? esc_attr($edit_agent->min_last_messages ?? 3) : 3; ?>">
                    </td>
                </tr>
                <tr class="summary-settings" style="<?php echo ($edit_agent && $edit_agent->summary_enabled) ? '' : 'display:none;'; ?>">
                    <th><label for="summary_model_id"><?php _e('Summarization Model', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <select id="summary_model_id" name="summary_model_id" class="regular-text">
                            <option value=""><?php _e('-- Use Default Model --', 'rds-ai-engine'); ?></option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo esc_attr($model->id); ?>" <?php selected($edit_agent ? ($edit_agent->summary_model_id ?? '') : '', $model->id); ?>>
                                    <?php echo esc_html($model->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label><?php _e('Available Tools', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <?php if (empty($all_tools)): ?>
                            <p><?php _e('No tools registered yet.', 'rds-ai-engine'); ?></p>
                        <?php else: ?>
                            <ul style="list-style: none;">
                                <?php foreach ($all_tools as $tool_name => $tool_data): ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="tools[]" value="<?php echo esc_attr($tool_name); ?>" checked>
                                        <strong><?php echo esc_html($tool_name); ?></strong>: <?php echo esc_html($tool_data['description']); ?>
                                    </label>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Привязка навыков -->
                <tr>
                    <th><label><?php _e('Available Skills', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <?php 
                        // Получаем все навыки
                        $all_skills = $skill_manager->get_all();
                        // Получаем текущие навыки агента (если редактируем)
                        $agent_skill_ids = [];
                        if ($edit_agent) {
                            $agent_skills_objs = $skill_manager->get_agent_skills($edit_agent->id);
                            foreach ($agent_skills_objs as $as) {
                                $agent_skill_ids[] = $as->id;
                            }
                        }

                        if (empty($all_skills)): ?>
                            <p><?php _e('No skills found. Go to the Skills tab to add some.', 'rds-ai-engine'); ?></p>
                        <?php else: ?>
                            <ul style="list-style: none;">
                                <?php foreach ($all_skills as $skill): ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo esc_attr($skill->id); ?>" 
                                            <?php checked(in_array($skill->id, $agent_skill_ids)); ?>>
                                        <strong><?php echo esc_html($skill->name); ?></strong>: <?php echo esc_html($skill->description); ?>
                                    </label>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                </tr>                
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo $edit_agent ? __('Update Agent', 'rds-ai-engine') : __('Add Agent', 'rds-ai-engine'); ?></button>
                <?php if ($edit_agent): ?>
                    <a href="?page=rds-aie&tab=agents" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Список агентов -->
    <div class="agents-list">
        <h2><?php _e('Available Agents', 'rds-ai-engine'); ?></h2>
        <?php if (empty($agents)): ?>
            <p><?php _e('No agents created yet.', 'rds-ai-engine'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Model', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Max Iterations', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Actions', 'rds-ai-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td><?php echo esc_html($agent->name); ?></td>
                            <td><?php echo esc_html($agent->default_model_id ?: '—'); ?></td>
                            <td><?php echo esc_html($agent->max_iterations); ?></td>
                            <td>
                                <a href="?page=rds-aie&tab=agents&edit=<?php echo esc_attr($agent->id); ?>" class="button button-small"><?php _e('Edit', 'rds-ai-engine'); ?></a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                    <?php wp_nonce_field('rds_aie_agents'); ?>
                                    <input type="hidden" name="action" value="delete_agent">
                                    <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php _e('Delete', 'rds-ai-engine'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Показать/скрыть настройки суммаризации
    $('#summary_enabled').change(function() {
        var isChecked = $(this).is(':checked');
        if (isChecked) {
            $('.summary-settings').show();
        } else {
            $('.summary-settings').hide();
        }
    });
});
</script>