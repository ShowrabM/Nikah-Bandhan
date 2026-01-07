<?php
if (!defined('ABSPATH')) exit;

class OVW_MAT_Blocks {

  public function __construct() {
    add_action('init', [$this, 'register']);
    add_filter('block_categories_all', [$this, 'categories'], 10, 2);
  }

  public function categories($categories, $post) {
    $categories[] = [
      'slug' => 'ovw-matrimonial',
      'title' => 'Matrimonial',
    ];
    return $categories;
  }

  public function register() {
    $handle = 'ovw-mat-blocks';
    wp_register_script(
      $handle,
      OVW_MAT_URL . 'assets/js/blocks.js',
      ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
      OVW_MAT_VER,
      true
    );

    $blocks = [
      'dashboard' => [
        'title' => 'Matrimonial Dashboard',
        'shortcode' => '[user_dashboard_ui]'
      ],
      'create' => [
        'title' => 'Matrimonial Biodata Form',
        'shortcode' => '[ovw_matrimonial_create]'
      ],
      'search' => [
        'title' => 'Matrimonial Search',
        'shortcode' => '[ovw_matrimonial_search]'
      ],
      'view' => [
        'title' => 'Matrimonial View',
        'shortcode' => '[ovw_matrimonial_view]'
      ],
    ];

    foreach ($blocks as $name => $config) {
      register_block_type('ovw-matrimonial/' . $name, [
        'api_version' => 2,
        'editor_script' => $handle,
        'render_callback' => function () use ($config) {
          return do_shortcode($config['shortcode']);
        },
        'attributes' => [
          'title' => [
            'type' => 'string',
            'default' => $config['title']
          ],
        ],
      ]);
    }
  }
}
