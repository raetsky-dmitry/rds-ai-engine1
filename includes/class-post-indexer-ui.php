<?php
/**
 * UI для ручной индексации записей в векторный поиск
 */
class RDS_AIE_Post_Indexer_UI {

    public function init() {
        // Добавляем метабокс для всех поддерживаемых типов записей
        add_action('add_meta_boxes', [$this, 'add_indexer_metabox']);
        
        // AJAX обработчики
        add_action('wp_ajax_rds_aie_index_post', [$this, 'ajax_index_post']);
        add_action('wp_ajax_rds_aie_check_index_status', [$this, 'ajax_check_status']);
        
        // Подключаем скрипты только на страницах редактирования
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Регистрация метабокса
     */
    public function add_indexer_metabox() {
        // Типы записей, для которых показываем кнопку
        $post_types = ['post', 'page', 'rds_aie_memory'];
        
        foreach ($post_types as $type) {
            add_meta_box(
                'rds_aie_vector_indexer',
                __('AI Vector Index', 'rds-ai-engine'),
                [$this, 'render_metabox'],
                $type,
                'side',      // Расположение: сайдбар
                'default'    // Приоритет
            );
        }
    }

    /**
     * Рендеринг содержимого метабокса
     */
    public function render_metabox($post) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rds_aie_embeddings';
        
        // Проверяем, проиндексирована ли уже эта запись
        $is_indexed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d",
            $post->ID
        ));
        
        $status_class = $is_indexed ? 'indexed' : 'not-indexed';
        $status_text = $is_indexed 
            ? __('✓ Indexed for AI Search', 'rds-ai-engine') 
            : __('Not indexed yet', 'rds-ai-engine');
        
        wp_nonce_field('rds_aie_index_post', 'rds_aie_index_nonce');
        ?>
        <div class="rds-aie-indexer-box">
            <p class="rds-aie-index-status <?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_text); ?>
            </p>
            
            <button type="button" 
                    id="rds-aie-index-btn" 
                    class="button button-primary" 
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    <?php disabled(!$post->post_content); ?>>
                <?php echo $is_indexed 
                    ? __('Re-index Document', 'rds-ai-engine') 
                    : __('Index for AI Search', 'rds-ai-engine'); ?>
            </button>
            
            <p class="description" style="margin-top: 8px;">
                <?php _e('Generates a summary and vector embedding to make this content searchable by meaning.', 'rds-ai-engine'); ?>
            </p>
            
            <div id="rds-aie-index-message" class="notice" style="display:none; margin-top:10px;"></div>
        </div>
        <?php
    }

    /**
     * Подключение JS/CSS только на нужных страницах
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        wp_enqueue_script(
            'rds-aie-indexer',
            RDS_AIE_PLUGIN_URL . 'admin/js/post-indexer.js',
            ['jquery'],
            RDS_AIE_VERSION,
            true
        );
        
        wp_localize_script('rds-aie-indexer', 'rdsAieIndexer', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rds_aie_index_post'),
            'indexing' => __('Indexing...', 'rds-ai-engine'),
            'success'  => __('Successfully indexed!', 'rds-ai-engine'),
            'error'    => __('Indexing failed.', 'rds-ai-engine')
        ]);
        
        // Минимальные стили для статуса
        wp_add_inline_style('common', '
            .rds-aie-index-status.indexed { color: #00a32a; font-weight: 600; }
            .rds-aie-index-status.not-indexed { color: #b32d2e; }
            .rds-aie-indexer-box .button { width: 100%; text-align: center; }
        ');
    }

    /**
     * AJAX: Индексация записи
     */
    public function ajax_index_post() {
        check_ajax_referer('rds_aie_index_post', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        try {
            $main = RDS_AIE_Main::get_instance();
            $rag_engine = $main->get_rag_engine();
            
            $success = $rag_engine->index_post($post_id);
            
            if ($success) {
                wp_send_json_success([
                    'message' => __('Document successfully indexed for vector search.', 'rds-ai-engine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to generate embedding. Check RAG settings.', 'rds-ai-engine')
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Проверка статуса (опционально)
     */
    public function ajax_check_status() {
        check_ajax_referer('rds_aie_index_post', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        global $wpdb;
        $table_name = $wpdb->prefix . 'rds_aie_embeddings';
        
        $is_indexed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d",
            $post_id
        ));
        
        wp_send_json_success(['indexed' => (bool)$is_indexed]);
    }
}