<?php
if (!defined('ABSPATH')) exit;

class OVW_MAT_Installer {

  public static function activate() {
    self::ensure_tables();
  }

  public static function ensure_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $entries = $wpdb->prefix . 'ovw_matrimonial_entries';
    $forms = $wpdb->prefix . 'ovw_matrimonial_forms';

    $has_entries = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $entries)) === $entries);
    $has_forms = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms)) === $forms);
    $has_form_id = false;
    $has_was_approved = false;
    $has_approved_at = false;
    if ($has_entries) {
      $col = $wpdb->get_var("SHOW COLUMNS FROM $entries LIKE 'form_id'");
      $has_form_id = !empty($col);
      $col = $wpdb->get_var("SHOW COLUMNS FROM $entries LIKE 'was_approved'");
      $has_was_approved = !empty($col);
      $col = $wpdb->get_var("SHOW COLUMNS FROM $entries LIKE 'approved_at'");
      $has_approved_at = !empty($col);
    }
    if ($has_entries && $has_forms && $has_form_id && $has_was_approved && $has_approved_at) return;

    if (!$has_entries) {
      $wpdb->query("CREATE TABLE $entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        form_id BIGINT UNSIGNED NULL,

        biodata_no VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        was_approved TINYINT(1) NOT NULL DEFAULT 0,
        approved_at DATETIME NULL,

        biodata_type VARCHAR(190) NULL,
        marital_status VARCHAR(190) NULL,
        present_address VARCHAR(190) NULL,

        age INT NULL,
        height VARCHAR(50) NULL,
        complexion VARCHAR(100) NULL,
        occupation VARCHAR(190) NULL,

        photo_url TEXT NULL,
        payload_json LONGTEXT NOT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY `status` (`status`),
        KEY `user_id` (`user_id`),
        KEY `form_id` (`form_id`),
        KEY `biodata_type` (`biodata_type`),
        KEY `marital_status` (`marital_status`),
        KEY `present_address` (`present_address`),
        KEY `age` (`age`)
      ) $charset;");
    }

    if (!$has_forms) {
      $wpdb->query("CREATE TABLE $forms (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(190) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'inactive',
        schema_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY `status` (`status`)
      ) $charset;");
    }

    if ($has_entries && !$has_form_id) {
      $wpdb->query("ALTER TABLE $entries ADD COLUMN form_id BIGINT UNSIGNED NULL");
      $idx = $wpdb->get_var("SHOW INDEX FROM $entries WHERE Key_name='form_id'");
      if (!$idx) {
        $wpdb->query("ALTER TABLE $entries ADD KEY `form_id` (`form_id`)");
      }
    }
    if ($has_entries && !$has_was_approved) {
      $wpdb->query("ALTER TABLE $entries ADD COLUMN was_approved TINYINT(1) NOT NULL DEFAULT 0");
    }
    if ($has_entries && !$has_approved_at) {
      $wpdb->query("ALTER TABLE $entries ADD COLUMN approved_at DATETIME NULL");
    }
  }
}
