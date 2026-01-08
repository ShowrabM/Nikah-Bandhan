<?php
if (!defined('ABSPATH')) exit;

class OVW_MAT_Rest {

  public function __construct() {
    add_action('rest_api_init', [$this, 'routes']);
  }

  public function routes() {

    register_rest_route('ovw-matrimonial/v1', '/schema', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => function (WP_REST_Request $req) {
        $form_id = (int)($req->get_param('form_id') ?? 0);
        return rest_ensure_response(ovw_mat_get_schema($form_id));
      },
    ]);

    register_rest_route('ovw-matrimonial/v1', '/submit', [
      'methods' => 'POST',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
      'callback' => [$this, 'submit'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/options', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'options'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/search', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'search'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/view', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'view'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/upload', [
      'methods' => 'POST',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
      'callback' => [$this, 'upload'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/admin-entry', [
      'methods' => 'GET',
      'permission_callback' => function () {
        return current_user_can('manage_options');
      },
      'callback' => [$this, 'admin_entry'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/my-biodata', [
      'methods' => 'GET',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
      'callback' => [$this, 'my_biodata'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/profile-photo', [
      'methods' => 'GET',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
      'callback' => [$this, 'profile_photo_get'],
    ]);

    register_rest_route('ovw-matrimonial/v1', '/profile-photo', [
      'methods' => 'POST',
      'permission_callback' => function () {
        return is_user_logged_in();
      },
      'callback' => [$this, 'profile_photo_set'],
    ]);
  }

  public function submit(WP_REST_Request $req) {
    $form_id = (int)($req->get_param('form_id') ?? 0);
    $is_draft = (bool)($req->get_param('draft') ?? false);
    $schema = ovw_mat_get_schema($form_id);
    $payload = $req->get_json_params();
    if (!is_array($payload)) $payload = [];

    $safe = ovw_mat_sanitize_payload($payload, $schema);

    $sys = ovw_mat_system_values($schema, $safe);

    // Index fields for search/cards
    $biodata_type    = $sys['biodata_type'] ?? ($safe['biodata_type'] ?? '');
    $marital_status  = $sys['marital_status'] ?? ($safe['marital_status'] ?? '');
    $present_address = $sys['present_address'] ?? ($safe['present_address'] ?? '');
    $height          = $sys['height'] ?? ($safe['height'] ?? '');
    $complexion      = $sys['complexion'] ?? ($safe['complexion'] ?? '');
    $occupation      = $sys['occupation'] ?? ($safe['occupation'] ?? '');
    $photo_url       = $sys['photo_url'] ?? ($safe['photo_url'] ?? '');
    $age             = ovw_mat_age_from_dob($sys['dob'] ?? ($safe['dob'] ?? ''));

    // Biodata prefix: ODM/ODF (optional logic)
    $prefix = (stripos($biodata_type, 'male') !== false) ? 'ODM' : 'ODF';
    $biodata_no = $prefix . '-' . wp_rand(10000, 99999);

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';

    $user_id = get_current_user_id();
    if ($form_id > 0) {
      $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, biodata_no, status, was_approved FROM $table WHERE user_id=%d AND form_id=%d ORDER BY id DESC LIMIT 1",
        $user_id,
        $form_id
      ), ARRAY_A);
    } else {
      $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, biodata_no, status, was_approved FROM $table WHERE user_id=%d AND (form_id IS NULL OR form_id=0) ORDER BY id DESC LIMIT 1",
        $user_id
      ), ARRAY_A);
    }

    $result = false;
    $resubmitted = false;
    if ($existing) {
      $was_approved = (int)($existing['was_approved'] ?? 0);
      if (($existing['status'] ?? '') === 'approved') {
        $was_approved = 1;
      }
      $resubmitted = $was_approved === 1;
      $next_status = ($is_draft && !$resubmitted) ? 'draft' : 'pending';

      $result = $wpdb->update($table, [
        'status' => $next_status,
        'was_approved' => $was_approved,

        'biodata_type' => $biodata_type,
        'marital_status' => $marital_status,
        'present_address' => $present_address,

        'age' => $age,
        'height' => $height,
        'complexion' => $complexion,
        'occupation' => $occupation,

        'photo_url' => $photo_url,
        'payload_json' => wp_json_encode($safe),
      ], ['id' => (int)$existing['id']]);

      if ($form_id > 0) {
        $wpdb->query($wpdb->prepare(
          "UPDATE $table SET status='archived' WHERE user_id=%d AND id<>%d AND form_id=%d",
          $user_id,
          (int)$existing['id'],
          $form_id
        ));
      } else {
        $wpdb->query($wpdb->prepare(
          "UPDATE $table SET status='archived' WHERE user_id=%d AND id<>%d AND (form_id IS NULL OR form_id=0)",
          $user_id,
          (int)$existing['id']
        ));
      }
    } else {
      $result = $wpdb->insert($table, [
        'user_id' => $user_id,
        'form_id' => $form_id ?: null,
        'biodata_no' => $biodata_no,
        'status' => $is_draft ? 'draft' : 'pending',
        'was_approved' => 0,

        'biodata_type' => $biodata_type,
        'marital_status' => $marital_status,
        'present_address' => $present_address,

        'age' => $age,
        'height' => $height,
        'complexion' => $complexion,
        'occupation' => $occupation,

        'photo_url' => $photo_url,
        'payload_json' => wp_json_encode($safe),
      ]);
    }

    if ($result === false) {
      return new WP_Error('db_error', 'Could not save biodata: ' . $wpdb->last_error, ['status' => 500]);
    }

    return rest_ensure_response([
      'ok' => true,
      'message' => $is_draft && !$resubmitted ? 'Your biodata has been saved as draft.' : ($resubmitted ? 'Your biodata update is pending re-approval.' : 'Your biodata is pending to approve.')
    ]);
  }

  public function options(WP_REST_Request $req) {
    if (!ovw_mat_user_can_view_biodata()) {
      return rest_ensure_response([
        'biodata_types' => [],
        'marital_statuses' => [],
        'present_addresses' => [],
        'message' => 'Membership required to view biodata.'
      ]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $form_id = (int)($req->get_param('form_id') ?? 0);
    $where = "status='approved'";
    $args = [];
    if ($form_id > 0) { $where .= " AND form_id=%d"; $args[] = $form_id; }

    $types_sql = "SELECT DISTINCT biodata_type FROM $table WHERE $where AND biodata_type<>'' ORDER BY biodata_type ASC";
    $mar_sql   = "SELECT DISTINCT marital_status FROM $table WHERE $where AND marital_status<>'' ORDER BY marital_status ASC";
    $addr_sql  = "SELECT DISTINCT present_address FROM $table WHERE $where AND present_address<>'' ORDER BY present_address ASC";

    $types = $args ? $wpdb->get_col($wpdb->prepare($types_sql, ...$args)) : $wpdb->get_col($types_sql);
    $mar   = $args ? $wpdb->get_col($wpdb->prepare($mar_sql, ...$args)) : $wpdb->get_col($mar_sql);
    $addr  = $args ? $wpdb->get_col($wpdb->prepare($addr_sql, ...$args)) : $wpdb->get_col($addr_sql);

    return rest_ensure_response([
      'biodata_types' => array_values(array_filter(array_map('strval', $types))),
      'marital_statuses' => array_values(array_filter(array_map('strval', $mar))),
      'present_addresses' => array_values(array_filter(array_map('strval', $addr))),
    ]);
  }

  public function search(WP_REST_Request $req) {
    if (!ovw_mat_user_can_view_biodata()) {
      return rest_ensure_response([
        'total' => 0,
        'page' => 1,
        'per_page' => 0,
        'items' => [],
        'message' => 'Membership required to view biodata.'
      ]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';

    $type = sanitize_text_field($req->get_param('biodata_type') ?? '');
    $mar  = sanitize_text_field($req->get_param('marital_status') ?? '');
    $addr = sanitize_text_field($req->get_param('present_address') ?? '');

    $page = max(1, (int)($req->get_param('page') ?? 1));
    $per_page = max(6, min(30, (int)($req->get_param('per_page') ?? 9)));
    $offset = ($page - 1) * $per_page;

    $where = "status='approved'";
    $args = [];
    $form_id = (int)($req->get_param('form_id') ?? 0);
    if ($form_id > 0) { $where .= " AND form_id=%d"; $args[] = $form_id; }

    if ($type !== '') { $where .= " AND biodata_type=%s"; $args[] = $type; }
    if ($mar  !== '') { $where .= " AND marital_status=%s"; $args[] = $mar; }
    if ($addr !== '') { $where .= " AND present_address=%s"; $args[] = $addr; }

    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
    $total = $args ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$args)) : (int)$wpdb->get_var($count_sql);

    $sql = "SELECT id, biodata_no, age, height, complexion, occupation
            FROM $table
            WHERE $where
            ORDER BY id DESC
            LIMIT %d OFFSET %d";

    $qargs = array_merge($args, [$per_page, $offset]);
    $items = $wpdb->get_results($wpdb->prepare($sql, ...$qargs), ARRAY_A);

    return rest_ensure_response([
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
      'items' => $items
    ]);
  }

  public function view(WP_REST_Request $req) {
    if (!ovw_mat_user_can_view_biodata()) {
      return new WP_Error('forbidden', 'Membership required to view biodata.', ['status' => 403]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $id = (int)($req->get_param('id') ?? 0);

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, biodata_no, payload_json, form_id FROM $table WHERE id=%d AND status='approved' LIMIT 1",
      $id
    ), ARRAY_A);

    if (!$row) return new WP_Error('not_found', 'Biodata not found', ['status'=>404]);

    $payload = json_decode($row['payload_json'], true);
    if (!is_array($payload)) $payload = [];

    return rest_ensure_response([
      'id' => (int)$row['id'],
      'biodata_no' => $row['biodata_no'],
      'payload' => $payload,
      'schema' => ovw_mat_get_schema((int)($row['form_id'] ?? 0))
    ]);
  }

  public function admin_entry(WP_REST_Request $req) {
    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $id = (int)($req->get_param('id') ?? 0);

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, biodata_no, status, payload_json, created_at, form_id FROM $table WHERE id=%d LIMIT 1",
      $id
    ), ARRAY_A);

    if (!$row) return new WP_Error('not_found', 'Not found', ['status'=>404]);

    $payload = json_decode($row['payload_json'], true);
    if (!is_array($payload)) $payload = [];

    return rest_ensure_response([
      'id' => (int)$row['id'],
      'biodata_no' => $row['biodata_no'],
      'status' => $row['status'],
      'created_at' => $row['created_at'],
      'payload' => $payload,
      'schema' => ovw_mat_get_schema((int)($row['form_id'] ?? 0))
    ]);
  }

  public function upload(WP_REST_Request $req) {
    if (empty($_FILES['photo'])) {
      return new WP_Error('missing_file', 'No file uploaded.', ['status' => 400]);
    }

    $file = $_FILES['photo'];
    if (!empty($file['error'])) {
      return new WP_Error('upload_error', 'Upload failed.', ['status' => 400]);
    }

    $max = 2 * 1024 * 1024;
    if (!empty($file['size']) && $file['size'] > $max) {
      return new WP_Error('file_too_large', 'Max file size is 2MB.', ['status' => 400]);
    }

    $type = wp_check_filetype($file['name']);
    if (empty($type['type']) || strpos($type['type'], 'image/') !== 0) {
      return new WP_Error('invalid_type', 'Only image uploads are allowed.', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($file, ['test_form' => false]);
    if (!empty($uploaded['error'])) {
      return new WP_Error('upload_failed', $uploaded['error'], ['status' => 400]);
    }

    return rest_ensure_response([
      'ok' => true,
      'url' => $uploaded['url']
    ]);
  }

  public function my_biodata(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $form_id = (int)($req->get_param('form_id') ?? 0);
    $row = ovw_mat_get_user_biodata($user_id, $form_id);
    if (!$row) {
      return rest_ensure_response(['ok' => true, 'entry' => null]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $full = $wpdb->get_row($wpdb->prepare(
      "SELECT id, biodata_no, status, payload_json FROM $table WHERE id=%d LIMIT 1",
      (int)$row['id']
    ), ARRAY_A);

    if (!$full) {
      return rest_ensure_response(['ok' => true, 'entry' => null]);
    }

    $payload = json_decode($full['payload_json'], true);
    if (!is_array($payload)) $payload = [];

    return rest_ensure_response([
      'ok' => true,
      'entry' => [
        'id' => (int)$full['id'],
        'status' => $full['status'],
        'biodata_no' => $full['biodata_no'],
        'payload' => $payload
      ]
    ]);
  }

  public function profile_photo_get() {
    $user_id = get_current_user_id();
    return rest_ensure_response([
      'ok' => true,
      'url' => ovw_mat_get_profile_photo_url($user_id)
    ]);
  }

  public function profile_photo_set(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $params = $req->get_json_params();
    $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
    if (!$url) {
      return new WP_Error('invalid_url', 'Missing image URL.', ['status' => 400]);
    }
    update_user_meta($user_id, OVW_MAT_PROFILE_PHOTO_META, $url);
    return rest_ensure_response(['ok' => true, 'url' => $url]);
  }

}
