<?php
/**
 * Collection Page custom post type and rewrite.
 *
 * Registers the collection_page CPT for collection landing pages.
 * Flush rewrite rules once per version via option check (themes cannot use activation hook).
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SS_COLLECTION_PAGE_REWRITE_VERSION', 1);

add_action('init', function () {
    $labels = [
        'name'                  => _x('Collection Pages', 'post type general name', 'stone-sparkle'),
        'singular_name'          => _x('Collection Page', 'post type singular name', 'stone-sparkle'),
        'menu_name'              => __('Collection Pages', 'stone-sparkle'),
        'add_new'                => __('Add New', 'stone-sparkle'),
        'add_new_item'           => __('Add New Collection Page', 'stone-sparkle'),
        'edit_item'              => __('Edit Collection Page', 'stone-sparkle'),
        'new_item'               => __('New Collection Page', 'stone-sparkle'),
        'view_item'              => __('View Collection Page', 'stone-sparkle'),
        'view_items'             => __('View Collection Pages', 'stone-sparkle'),
        'search_items'           => __('Search Collection Pages', 'stone-sparkle'),
        'not_found'              => __('No collection pages found.', 'stone-sparkle'),
        'not_found_in_trash'     => __('No collection pages found in Trash.', 'stone-sparkle'),
        'all_items'              => __('Collection Pages', 'stone-sparkle'),
        'item_published'         => __('Collection page published.', 'stone-sparkle'),
        'item_updated'           => __('Collection page updated.', 'stone-sparkle'),
    ];

    register_post_type('collection_page', [
        'labels'              => $labels,
        'public'               => true,
        'publicly_queryable'    => true,
        'show_ui'              => true,
        'show_in_menu'         => true,
        'query_var'            => true,
        'rewrite'              => ['slug' => 'collection'],
        'capability_type'      => 'post',
        'has_archive'          => false,
        'hierarchical'         => false,
        'menu_position'        => 56,
        'menu_icon'            => 'dashicons-images-alt2',
        'supports'             => ['title', 'editor', 'thumbnail', 'excerpt'],
    ]);

    // One-time rewrite flush when version bumps (themes cannot use register_activation_hook).
    $saved = (int) get_option('ss_collection_page_rewrite_version', 0);
    if ($saved < SS_COLLECTION_PAGE_REWRITE_VERSION) {
        flush_rewrite_rules();
        update_option('ss_collection_page_rewrite_version', SS_COLLECTION_PAGE_REWRITE_VERSION);
    }
}, 0);
