<?php
/**
 * Вкладка управления навыками (Skills)
 */
$main = RDS_AIE_Main::get_instance();

// Инициализируем Skill Manager если еще не создан
if (!class_exists('RDS_AIE_Skill_Manager')) {
    require_once RDS_AIE_PLUGIN_DIR . 'includes/class-skill-manager.php';
}
$skill_manager = new RDS_AIE_Skill_Manager($main->get_db());

$skills = $skill_manager->get_all();
$message = '';

// Данные для редактирования
$edit_skill = null;
if (isset($_GET['edit_skill'])) {
    $edit_skill = $skill_manager->get(intval($_GET['edit_skill']));
}

// Обработка действий
if (isset($_POST['action'])) {
    $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
    if (wp_verify_nonce($nonce, 'rds_aie_skills')) {
        try {
            switch ($_POST['action']) {
                case 'save_skill':
                    $data = [
                        'id' => isset($_POST['skill_id']) ? intval($_POST['skill_id']) : 0,
                        'name' => sanitize_key($_POST['name']),
                        'description' => sanitize_textarea_field($_POST['description']),
                        'url' => esc_url_raw($_POST['url'])
                    ];
                    $skill_manager->register($data);
                    $message = '<div class="notice notice-success"><p>' . __('Skill saved successfully.', 'rds-ai-engine') . '</p></div>';
                    
                    // Сбрасываем режим редактирования после сохранения
                    $edit_skill = null;
                    $skills = $skill_manager->get_all();
                    break;
                
                case 'import_json':
                    $json_data = json_decode(stripslashes($_POST['json_data']), true);
                    if (is_array($json_data)) {
                        $count = 0;
                        foreach ($json_data as $item) {
                            if (isset($item['name']) && isset($item['url'])) {
                                $skill_manager->register([
                                    'name' => $item['name'],
                                    'description' => $item['description'] ?? '',
                                    'url' => $item['url']
                                ]);
                                $count++;
                            }
                        }
                        $message = '<div class="notice notice-success"><p>' . sprintf(__('Successfully imported %d skills.', 'rds-ai-engine'), $count) . '</p></div>';
                        $skills = $skill_manager->get_all();
                    } else {
                        throw new Exception(__('Invalid JSON format.', 'rds-ai-engine'));
                    }
                    break;

                case 'delete_skill':
                    $skill_id = intval($_POST['skill_id']);
                    $skill_manager->delete($skill_id);
                    $message = '<div class="notice notice-success"><p>' . __('Skill deleted successfully.', 'rds-ai-engine') . '</p></div>';
                    
                    // Если удаляли редактируемый навык, сбрасываем форму
                    if ($edit_skill && $edit_skill->id == $skill_id) {
                        $edit_skill = null;
                    }
                    $skills = $skill_manager->get_all();
                    break;
            }
        } catch (Exception $e) {
            $message = '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}
?>

<div class="wrap rds-aie-skills">
    <h1><?php _e('AI Skills', 'rds-ai-engine'); ?></h1>
    <?php echo $message; ?>

    <!-- Форма добавления/редактирования -->
    <div class="skill-form">
        <h2><?php echo $edit_skill ? __('Edit Skill', 'rds-ai-engine') : __('Add New Skill', 'rds-ai-engine'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('rds_aie_skills'); ?>
            <input type="hidden" name="action" value="save_skill">
            <input type="hidden" name="skill_id" value="<?php echo $edit_skill ? esc_attr($edit_skill->id) : ''; ?>">
            
            <table class="form-table">
                <tr>
                    <th><label for="name"><?php _e('Name (Slug)', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="text" id="name" name="name" class="regular-text" required 
                               value="<?php echo $edit_skill ? esc_attr($edit_skill->name) : ''; ?>"
                               placeholder="e.g., seo_analyst" 
                               <?php echo $edit_skill ? 'readonly' : ''; ?>>
                        <p class="description"><?php _e('Unique identifier for the skill. Cannot be changed after creation.', 'rds-ai-engine'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="description"><?php _e('Description', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <textarea id="description" name="description" rows="3" class="large-text"><?php echo $edit_skill ? esc_textarea($edit_skill->description) : ''; ?></textarea>
                        <p class="description"><?php _e('Short description for the agent to understand when to use this skill.', 'rds-ai-engine'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="url"><?php _e('File Path or URL', 'rds-ai-engine'); ?></label></th>
                    <td>
                        <input type="text" id="url" name="url" class="large-text" required 
                               value="<?php echo $edit_skill ? esc_attr($edit_skill->url) : ''; ?>"
                               placeholder="/var/www/.../skills/my-skill.md or https://...">
                        <p class="description"><?php _e('Absolute path to local .md file or external URL.', 'rds-ai-engine'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo $edit_skill ? __('Update Skill', 'rds-ai-engine') : __('Add Skill', 'rds-ai-engine'); ?></button>
                <?php if ($edit_skill): ?>
                    <a href="?page=rds-aie&tab=skills" class="button"><?php _e('Cancel', 'rds-ai-engine'); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <hr>

    <div class="skill-import">
        <h2><?php _e('Bulk Import (JSON)', 'rds-ai-engine'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('rds_aie_skills'); ?>
            <input type="hidden" name="action" value="import_json">
            <p class="description"><?php _e('Paste a JSON array of skills: [{"name": "...", "url": "...", "description": "..."}]', 'rds-ai-engine'); ?></p>
            <textarea name="json_data" rows="4" class="large-text"></textarea>
            <p class="submit">
                <button type="submit" class="button button-secondary"><?php _e('Import Skills', 'rds-ai-engine'); ?></button>
            </p>
        </form>
    </div>

    <hr>

    <div class="skills-list">
        <h2><?php _e('Available Skills', 'rds-ai-engine'); ?></h2>
        <?php if (empty($skills)): ?>
            <p><?php _e('No skills registered yet.', 'rds-ai-engine'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Description', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Source', 'rds-ai-engine'); ?></th>
                        <th><?php _e('Actions', 'rds-ai-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $skill): ?>
                        <tr>
                            <td><?php echo esc_html($skill->name); ?></td>
                            <td><?php echo esc_html($skill->description); ?></td>
                            <td><code><?php echo esc_html(substr($skill->url, 0, 50)); ?>...</code></td>
                            <td>
                                <a href="?page=rds-aie&tab=skills&edit_skill=<?php echo esc_attr($skill->id); ?>" class="button button-small"><?php _e('Edit', 'rds-ai-engine'); ?></a>
                                
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                    <?php wp_nonce_field('rds_aie_skills'); ?>
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="skill_id" value="<?php echo esc_attr($skill->id); ?>">
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