<?php
/**
 * Регистрация Custom Post Type и Таксономий для долгосрочной памяти агентов
 */
class RDS_AIE_Memory_CPT {

    const POST_TYPE = 'rds_aie_memory';
    const TAXONOMY = 'memory_category'; 

    /**
     * Инициализация хуков
     */
    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
    }

    /**
     * Регистрация типа записи
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Documents', 'Post Type General Name', 'rds-ai-engine'), // <-- Изменили на Документы
            'singular_name'         => _x('Document', 'Post Type Singular Name', 'rds-ai-engine'),
            'menu_name'             => __('Documents', 'rds-ai-engine'),
            'name_admin_bar'        => __('Document', 'rds-ai-engine'),
            'archives'              => __('Document Archives', 'rds-ai-engine'),
            'all_items'             => __('All Documents', 'rds-ai-engine'),
            'add_new_item'          => __('Add New Document', 'rds-ai-engine'),
            'add_new'               => __('Add New', 'rds-ai-engine'),
            'new_item'              => __('New Document', 'rds-ai-engine'),
            'edit_item'             => __('Edit Document', 'rds-ai-engine'),
            'update_item'           => __('Update Document', 'rds-ai-engine'),
            'view_item'             => __('View Document', 'rds-ai-engine'),
            'search_items'          => __('Search Documents', 'rds-ai-engine'),
            'not_found'             => __('Not found', 'rds-ai-engine'),
            'not_found_in_trash'    => __('Not found in Trash', 'rds-ai-engine'),
        ];

        $args = [
            'label'                 => __('Documents', 'rds-ai-engine'),
            'description'           => __('Long-term memory storage for AI Agents', 'rds-ai-engine'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'custom-fields', 'revisions', 'excerpt'],
            'hierarchical'          => false,
            'public'                => false,      
            'show_ui'               => true,       
            'show_in_menu'          => true,       
            'menu_position'         => 30,         
            'menu_icon'             => 'dashicons-media-document', // <-- Стандартная иконка документа
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,       
            'publicly_queryable'    => false,      
            'capability_type'       => 'post',     
            'map_meta_cap'          => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Регистрация собственной таксономии "Memory Categories"
     */
    public function register_taxonomy() {
        $labels = [
            'name'                       => _x('Categories', 'Taxonomy General Name', 'rds-ai-engine'),
            'singular_name'              => _x('Category', 'Taxonomy Singular Name', 'rds-ai-engine'),
            'menu_name'                  => __('Categories', 'rds-ai-engine'),
            'all_items'                  => __('All Categories', 'rds-ai-engine'),
            'parent_item'                => __('Parent Category', 'rds-ai-engine'),
            'parent_item_colon'          => __('Parent Category:', 'rds-ai-engine'),
            'new_item_name'              => __('New Category Name', 'rds-ai-engine'),
            'add_new_item'               => __('Add New Category', 'rds-ai-engine'),
            'edit_item'                  => __('Edit Category', 'rds-ai-engine'),
            'update_item'                => __('Update Category', 'rds-ai-engine'),
            'view_item'                  => __('View Category', 'rds-ai-engine'),
            'separate_items_with_commas' => __('Separate categories with commas', 'rds-ai-engine'),
            'add_or_remove_items'        => __('Add or remove categories', 'rds-ai-engine'),
            'choose_from_most_used'      => __('Choose from the most used', 'rds-ai-engine'),
            'popular_items'              => __('Popular Categories', 'rds-ai-engine'),
            'search_items'               => __('Search Categories', 'rds-ai-engine'),
            'not_found'                  => __('Not Found', 'rds-ai-engine'),
            'no_terms'                   => __('No categories', 'rds-ai-engine'),
            'items_list'                 => __('Categories list', 'rds-ai-engine'),
            'items_list_navigation'      => __('Categories list navigation', 'rds-ai-engine'),
        ];

        $args = [
            'labels'                     => $labels,
            'hierarchical'               => true, 
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'rewrite'                    => false, 
        ];

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], $args);
        
        // Опционально: можно добавить и теги
        register_taxonomy_for_object_type('post_tag', self::POST_TYPE);
    }
}