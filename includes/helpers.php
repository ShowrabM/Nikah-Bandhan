<?php
if (!defined('ABSPATH')) exit;

const OVW_MAT_DASHBOARD_OPTION = 'ovw_mat_dashboard_pages';
const OVW_MAT_PROFILE_PHOTO_META = 'ovw_mat_profile_photo';
const OVW_MAT_EMAIL_OPTION = 'ovw_mat_email_settings';
const OVW_MAT_AUTO_PAGES_OPTION = 'ovw_mat_auto_pages';

function ovw_mat_forms_table() {
  global $wpdb;
  return $wpdb->prefix . 'ovw_matrimonial_forms';
}

function ovw_mat_default_schema() {
  return [
    'title' => 'Bio Form',
    'steps' => []
  ];
}

function ovw_mat_get_schema($form_id = 0) {
  $form_id = (int)$form_id;
  if ($form_id > 0) {
    $form = ovw_mat_get_form($form_id);
    if ($form && is_array($form['schema'])) return $form['schema'];
  }

  $active = ovw_mat_get_active_form();
  if ($active && is_array($active['schema'])) return $active['schema'];

  return ovw_mat_default_schema();
}

function ovw_mat_get_forms() {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $rows = $wpdb->get_results("SELECT id, title, status, created_at, updated_at FROM $table ORDER BY id DESC", ARRAY_A);
  return $rows ?: [];
}

function ovw_mat_get_form($id) {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id), ARRAY_A);
  if (!$row) return null;
  $row['schema'] = json_decode($row['schema_json'], true);
  if (!is_array($row['schema'])) $row['schema'] = ovw_mat_default_schema();
  return $row;
}

function ovw_mat_get_active_form() {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $row = $wpdb->get_row("SELECT * FROM $table WHERE status='active' ORDER BY id DESC LIMIT 1", ARRAY_A);
  if (!$row) return null;
  $row['schema'] = json_decode($row['schema_json'], true);
  if (!is_array($row['schema'])) $row['schema'] = ovw_mat_default_schema();
  return $row;
}

function ovw_mat_save_form($id, $title, $schema) {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $data = [
    'title' => $title ?: 'Bio Form',
    'schema_json' => wp_json_encode($schema),
  ];

  if ($id) {
    $wpdb->update($table, $data, ['id' => (int)$id]);
    return (int)$id;
  }

  $wpdb->insert($table, array_merge($data, ['status' => 'inactive']));
  return (int)$wpdb->insert_id;
}

function ovw_mat_set_active_form($id) {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $wpdb->query("UPDATE $table SET status='inactive'");
  $wpdb->update($table, ['status' => 'active'], ['id' => (int)$id]);
}

function ovw_mat_duplicate_form($id) {
  $form = ovw_mat_get_form($id);
  if (!$form) return 0;
  return ovw_mat_save_form(0, $form['title'] . ' (Copy)', $form['schema']);
}

function ovw_mat_delete_form($id) {
  global $wpdb;
  $table = ovw_mat_forms_table();
  $wpdb->delete($table, ['id' => (int)$id]);
}

function ovw_mat_export_form($id) {
  $form = ovw_mat_get_form($id);
  if (!$form) return null;
  return [
    'title' => $form['title'],
    'schema' => $form['schema']
  ];
}

function ovw_mat_age_from_dob($dob) {
  if (!$dob) return null;
  try {
    $dt = new DateTime($dob);
    $now = new DateTime('now');
    $diff = $now->diff($dt);
    return isset($diff->y) ? (int)$diff->y : null;
  } catch (Exception $e) {
    return null;
  }
}

function ovw_mat_sanitize_payload($payload, $schema) {
  // Build a map of key => type
  $field_types = [];
  if (!empty($schema['steps'])) {
    foreach (($schema['steps'] ?? []) as $step) {
      foreach (($step['rows'] ?? []) as $row) {
        foreach (($row['columns'] ?? []) as $col) {
          foreach (($col['fields'] ?? []) as $f) {
            if (!empty($f['key'])) {
              $field_types[$f['key']] = $f['type'] ?? 'text';
            }
          }
        }
      }
    }
  } else {
    foreach (($schema['sections'] ?? []) as $sec) {
      foreach (($sec['fields'] ?? []) as $f) {
        if (!empty($f['key'])) {
          $field_types[$f['key']] = $f['type'] ?? 'text';
        }
      }
    }
  }

  $out = [];
  foreach ($payload as $k => $v) {
    if (!isset($field_types[$k])) continue;
    $type = $field_types[$k];

    if (is_array($v)) {
      $out[$k] = array_map('sanitize_text_field', $v);
    } else {
      $val = is_scalar($v) ? (string)$v : '';
      $out[$k] = ($type === 'textarea') ? sanitize_textarea_field($val) : sanitize_text_field($val);
    }
  }
  return $out;
}

function ovw_mat_system_values($schema, $safe) {
  $map = [];

  $assign = function ($f) use (&$map, $safe) {
    if (!empty($f['system']) && !empty($f['key']) && isset($safe[$f['key']])) {
      $map[$f['system']] = $safe[$f['key']];
    }
  };

  if (!empty($schema['steps'])) {
    foreach (($schema['steps'] ?? []) as $step) {
      foreach (($step['rows'] ?? []) as $row) {
        foreach (($row['columns'] ?? []) as $col) {
          foreach (($col['fields'] ?? []) as $f) {
            $assign($f);
          }
        }
      }
    }
  } else {
    foreach (($schema['sections'] ?? []) as $sec) {
      foreach (($sec['fields'] ?? []) as $f) {
        $assign($f);
      }
    }
  }

  return $map;
}


function ovw_mat_user_has_approved_biodata($user_id) {
  $user_id = (int)$user_id;
  if ($user_id <= 0) return false;

  global $wpdb;
  $table = $wpdb->prefix . 'ovw_matrimonial_entries';

  $count = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table WHERE user_id=%d AND status='approved'",
    $user_id
  ));

  return $count > 0;
}

function ovw_mat_biodata_page_url() {
  $url = home_url('/create-biodata/');
  return apply_filters('ovw_mat_biodata_page_url', $url);
}

function ovw_mat_membership_page_url() {
  $url = function_exists('pmpro_url') ? pmpro_url('levels') : home_url('/membership/');
  return apply_filters('ovw_mat_membership_page_url', $url);
}

function ovw_mat_user_can_view_biodata($user_id = 0) {
  if (current_user_can('manage_options')) return true;
  $user_id = $user_id ? (int)$user_id : get_current_user_id();
  if (!function_exists('pmpro_hasMembershipLevel')) {
    return $user_id > 0;
  }
  if ($user_id <= 0) return false;
  return (bool)pmpro_hasMembershipLevel(null, $user_id);
}

function ovw_mat_get_profile_photo_url($user_id, $form_id = 0) {
  $user_id = (int)$user_id;
  if ($user_id <= 0) return '';

  $meta = get_user_meta($user_id, OVW_MAT_PROFILE_PHOTO_META, true);
  if (!empty($meta)) return esc_url_raw($meta);

  global $wpdb;
  $table = $wpdb->prefix . 'ovw_matrimonial_entries';
  $form_id = (int)$form_id;

  if ($form_id > 0) {
    $url = $wpdb->get_var($wpdb->prepare(
      "SELECT photo_url FROM $table WHERE user_id=%d AND form_id=%d AND status<>'archived' ORDER BY id DESC LIMIT 1",
      $user_id,
      $form_id
    ));
  } else {
    $url = $wpdb->get_var($wpdb->prepare(
      "SELECT photo_url FROM $table WHERE user_id=%d AND (form_id IS NULL OR form_id=0) AND status<>'archived' ORDER BY id DESC LIMIT 1",
      $user_id
    ));
  }

  return $url ? esc_url_raw($url) : '';
}

function ovw_mat_email_settings() {
  $defaults = [
    'shortlist_subject' => 'You have been shortlisted',
    'shortlist_body' => "Hi {user_name},\n\nYour biodata has been shortlisted.\n\nThanks.",
    'ignored_subject' => 'Your biodata was ignored',
    'ignored_body' => "Hi {user_name},\n\nYour biodata was ignored.\n\nThanks.",
  ];
  $saved = get_option(OVW_MAT_EMAIL_OPTION);
  if (!is_array($saved)) $saved = [];
  return array_merge($defaults, $saved);
}

function ovw_mat_update_email_settings($data) {
  $clean = [];
  $defaults = ovw_mat_email_settings();
  foreach ($defaults as $key => $val) {
    $clean[$key] = isset($data[$key]) ? wp_kses_post(wp_unslash($data[$key])) : $val;
  }
  update_option(OVW_MAT_EMAIL_OPTION, $clean);
}

function ovw_mat_dashboard_settings() {
  $defaults = [
    'edit_page_id' => 0,
    'delete_page_id' => 0,
    'shortlist_page_id' => 0,
    'ignore_page_id' => 0,
    'purchased_page_id' => 0,
    'support_page_id' => 0,
    'settings_page_id' => 0,
    'add_biodata_page_id' => 0,
  ];
  $saved = get_option(OVW_MAT_DASHBOARD_OPTION);
  if (!is_array($saved)) $saved = [];
  return array_merge($defaults, array_map('intval', $saved));
}

function ovw_mat_update_dashboard_settings($data) {
  $clean = [];
  foreach (ovw_mat_dashboard_settings() as $key => $val) {
    $clean[$key] = isset($data[$key]) ? (int)$data[$key] : 0;
  }
  update_option(OVW_MAT_DASHBOARD_OPTION, $clean);
}

function ovw_mat_get_auto_pages() {
  $pages = get_option(OVW_MAT_AUTO_PAGES_OPTION);
  return is_array($pages) ? $pages : [];
}

function ovw_mat_set_auto_pages($pages) {
  update_option(OVW_MAT_AUTO_PAGES_OPTION, $pages);
}

function ovw_mat_get_user_biodata($user_id, $form_id = 0) {
  $user_id = (int)$user_id;
  $form_id = (int)$form_id;
  if ($user_id <= 0) return null;

  global $wpdb;
  $table = $wpdb->prefix . 'ovw_matrimonial_entries';

  if ($form_id > 0) {
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, status, was_approved, biodata_no FROM $table WHERE user_id=%d AND form_id=%d AND status<>'archived' ORDER BY id DESC LIMIT 1",
      $user_id,
      $form_id
    ), ARRAY_A);
  } else {
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, status, was_approved, biodata_no FROM $table WHERE user_id=%d AND (form_id IS NULL OR form_id=0) AND status<>'archived' ORDER BY id DESC LIMIT 1",
      $user_id
    ), ARRAY_A);
  }

  return $row ?: null;
}
