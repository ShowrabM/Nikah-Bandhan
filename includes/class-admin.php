<?php
if (!defined('ABSPATH')) exit;

class OVW_MAT_Admin {

  public function __construct() {
    add_action('admin_menu', [$this, 'menu']);
    add_action('admin_enqueue_scripts', [$this, 'assets']);

    add_action('admin_post_ovw_mat_save_form', [$this, 'save_form']);
    add_action('admin_post_ovw_mat_set_active', [$this, 'set_active_form']);
    add_action('admin_post_ovw_mat_duplicate_form', [$this, 'duplicate_form']);
    add_action('admin_post_ovw_mat_delete_form', [$this, 'delete_form']);
    add_action('admin_post_ovw_mat_export_form', [$this, 'export_form']);
    add_action('admin_post_ovw_mat_save_dashboard_settings', [$this, 'save_dashboard_settings']);
    add_action('admin_post_ovw_mat_save_email_settings', [$this, 'save_email_settings']);
    add_action('admin_post_ovw_mat_autoset_pages', [$this, 'autoset_pages']);
    add_action('admin_post_ovw_mat_reset_settings', [$this, 'reset_settings']);
    add_filter('display_post_states', [$this, 'post_states'], 10, 2);
    add_action('admin_post_ovw_mat_approve', [$this, 'approve']);
    add_action('admin_post_ovw_mat_reject', [$this, 'reject']);
    add_action('admin_post_ovw_mat_reapprove', [$this, 'reapprove']);
    add_action('admin_post_ovw_mat_reapprove_all', [$this, 'reapprove_all']);
    add_action('admin_post_ovw_mat_delete', [$this, 'delete']);
    add_action('wp_ajax_ovw_mat_admin_entry', [$this, 'ajax_admin_entry']);
  }

  public function menu() {
    add_menu_page(
      'Matrimonial by OVW',
      'Matrimonial',
      'manage_options',
      'ovw-mat-pending',
      [$this, 'page_pending'],
      'dashicons-id',
      26
    );

    add_submenu_page('ovw-mat-pending', 'Forms', 'Forms', 'manage_options', 'ovw-mat-forms', [$this, 'page_forms']);
    add_submenu_page('ovw-mat-pending', 'Pending', 'Pending', 'manage_options', 'ovw-mat-pending', [$this, 'page_pending']);
    add_submenu_page('ovw-mat-pending', 'Approved', 'Approved', 'manage_options', 'ovw-mat-approved', [$this, 'page_approved']);
    add_submenu_page('ovw-mat-pending', 'Rejected', 'Rejected', 'manage_options', 'ovw-mat-rejected', [$this, 'page_rejected']);
    add_submenu_page('ovw-mat-pending', 'Settings', 'Settings', 'manage_options', 'ovw-mat-settings', [$this, 'page_settings']);
  }

  public function assets($hook) {
    if (strpos($hook, 'ovw-mat') === false) return;
    wp_enqueue_style('ovw-mat-admin', OVW_MAT_URL . 'assets/css/admin.css', [], OVW_MAT_VER);
    wp_enqueue_script('ovw-mat-admin-view', OVW_MAT_URL . 'assets/js/admin-view.js', [], OVW_MAT_VER, true);
    wp_localize_script('ovw-mat-admin-view', 'OVW_MAT_ADMIN', [
      'rest' => esc_url_raw(rest_url('ovw-matrimonial/v1')),
      'nonce' => wp_create_nonce('wp_rest'),
      'ajax' => admin_url('admin-ajax.php'),
    ]);
    if (strpos($hook, 'ovw-mat-forms') !== false) {
      wp_enqueue_style('ovw-mat-builder', OVW_MAT_URL . 'assets/css/admin-builder.css', [], OVW_MAT_VER);
      wp_enqueue_script('ovw-mat-builder', OVW_MAT_URL . 'assets/js/admin-builder.js', [], OVW_MAT_VER, true);
    }
  }

  public function page_forms() {
    if (!current_user_can('manage_options')) return;

    $action = sanitize_text_field($_GET['action'] ?? '');
    $form_id = (int)($_GET['form_id'] ?? 0);

    if ($action === 'preview') {
      $form = ovw_mat_get_form($form_id);
      if (!$form) { echo '<div class="wrap"><p>Form not found.</p></div>'; return; }
      wp_enqueue_style('ovw-mat-form', OVW_MAT_URL . 'assets/css/form.css', [], OVW_MAT_VER);
      wp_enqueue_script('ovw-mat-form', OVW_MAT_URL . 'assets/js/form.js', [], OVW_MAT_VER, true);
      wp_localize_script('ovw-mat-form', 'OVW_MAT', [
        'rest' => esc_url_raw(rest_url('ovw-matrimonial/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
      ]);
      ?>
      <div class="wrap">
        <h1>Preview: <?php echo esc_html($form['title']); ?></h1>
      </div>
      <?php
      echo (new OVW_MAT_Shortcodes())->create(['form_id' => (int)$form_id]);
      return;
    }

    if ($action === 'edit') {
      $form = $form_id ? ovw_mat_get_form($form_id) : null;
      $schema = $form['schema'] ?? ovw_mat_default_schema();
      $title = $form['title'] ?? 'Bio Form';
      ?>
      <div class="wrap">
        <h1><?php echo $form_id ? 'Edit Form' : 'Add New Form'; ?></h1>
        <p>Drag and drop fields to build your biodata form. This form appears where you use <code>[ovw_matrimonial_create form_id="<?php echo (int)$form_id; ?>"]</code>.</p>
        <p><strong>Tip:</strong> Set “System Field” for search filters (Biodata Type, Marital Status, Address, etc.).</p>
        <?php if (!empty($_GET['saved'])): ?>
          <div class="notice notice-success"><p>Form saved.</p></div>
        <?php elseif (!empty($_GET['error'])): ?>
          <div class="notice notice-error"><p>Could not save form. Please fix the builder data.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="ovw_mat_save_form">
          <input type="hidden" name="form_id" value="<?php echo (int)$form_id; ?>">
          <?php wp_nonce_field('ovw_mat_schema', 'ovw_mat_schema_nonce'); ?>

          <div class="ovw-form-title">
            <label for="ovw_form_title"><strong>Form Title</strong></label>
            <input type="text" id="ovw_form_title" name="form_title" class="regular-text" value="<?php echo esc_attr($title); ?>">
          </div>

          <div class="ovw-builder" id="ovwBuilder" data-schema="<?php echo esc_attr(wp_json_encode($schema)); ?>">
            <div class="ovw-builder-left">
              <div class="ovw-panel-title">Input Fields</div>
              <div class="ovw-palette">
                <button type="button" class="ovw-palette-item" data-type="text">Text</button>
                <button type="button" class="ovw-palette-item" data-type="textarea">Text Area</button>
                <button type="button" class="ovw-palette-item" data-type="email">Email</button>
                <button type="button" class="ovw-palette-item" data-type="phone">Phone</button>
                <button type="button" class="ovw-palette-item" data-type="date">Date</button>
                <button type="button" class="ovw-palette-item" data-type="address">Address</button>
                <button type="button" class="ovw-palette-item" data-type="country">Country List</button>
                <button type="button" class="ovw-palette-item" data-type="photo">Profile Image</button>
                <button type="button" class="ovw-palette-item" data-type="select">Dropdown</button>
                <button type="button" class="ovw-palette-item" data-type="radio">Radio</button>
                <button type="button" class="ovw-palette-item" data-type="checkbox">Checkbox</button>
                <button type="button" class="ovw-palette-item" data-type="multichoice">Multiple Choice</button>
              </div>
            </div>

            <div class="ovw-builder-center">
              <div class="ovw-builder-toolbar">
                <button type="button" class="button" id="ovw_add_step">Add Step</button>
                <button type="button" class="button" id="ovw_add_row">Add Row</button>
                <button type="button" class="button" id="ovw_add_row_2">Add 2 Columns</button>
              </div>
              <div class="ovw-steps" id="ovwSteps"></div>
              <div class="ovw-canvas" id="ovwCanvas"></div>
            </div>

            <div class="ovw-builder-right">
              <div class="ovw-panel-title">Field Settings</div>
              <div id="ovwFieldSettings" class="ovw-settings-empty">Select a field to edit settings.</div>
            </div>
          </div>

          <textarea name="schema_json" id="ovw_schema_json" style="display:none;"><?php echo esc_textarea(wp_json_encode($schema)); ?></textarea>

          <?php submit_button('Save Form'); ?>
        </form>
      </div>
      <?php
      return;
    }

    $forms = ovw_mat_get_forms();
    $active = ovw_mat_get_active_form();
    $active_id = $active['id'] ?? 0;
    ?>
    <div class="wrap">
      <h1>Forms <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-forms&action=edit')); ?>" class="page-title-action">Add New</a></h1>

      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Shortcode</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$forms): ?>
          <tr><td colspan="5">No forms yet.</td></tr>
        <?php else: foreach ($forms as $f): ?>
          <tr>
            <td><?php echo (int)$f['id']; ?></td>
            <td><strong><?php echo esc_html($f['title']); ?></strong></td>
            <td><?php echo $f['id'] == $active_id ? '<span style="color:#16a34a;font-weight:700;">Active</span>' : 'Inactive'; ?></td>
            <td><code>[ovw_matrimonial_create form_id="<?php echo (int)$f['id']; ?>"]</code></td>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-forms&action=edit&form_id='.(int)$f['id'])); ?>">Edit</a> |
              <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-forms&action=preview&form_id='.(int)$f['id'])); ?>">Preview</a> |
              <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-pending&form_id='.(int)$f['id'])); ?>">Entries</a> |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ovw_mat_set_active&id='.(int)$f['id']), 'ovw_mat_set_active_'.(int)$f['id'])); ?>">Set Active</a> |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ovw_mat_duplicate_form&id='.(int)$f['id']), 'ovw_mat_duplicate_form_'.(int)$f['id'])); ?>">Duplicate</a> |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ovw_mat_export_form&id='.(int)$f['id']), 'ovw_mat_export_form_'.(int)$f['id'])); ?>">Export</a> |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ovw_mat_delete_form&id='.(int)$f['id']), 'ovw_mat_delete_form_'.(int)$f['id'])); ?>" onclick="return confirm('Delete this form?');">Delete</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  public function save_form() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_schema', 'ovw_mat_schema_nonce');

    $form_id = (int)($_POST['form_id'] ?? 0);
    $title = sanitize_text_field($_POST['form_title'] ?? 'Bio Form');
    $raw = wp_unslash($_POST['schema_json'] ?? '');
    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
      wp_redirect(admin_url('admin.php?page=ovw-mat-forms&action=edit&form_id='.$form_id.'&error=1'));
      exit;
    }

    if (!isset($decoded['steps']) || !is_array($decoded['steps'])) {
      $decoded['steps'] = [];
    }

    $id = ovw_mat_save_form($form_id, $title, $decoded);
    if (!$id) {
      wp_redirect(admin_url('admin.php?page=ovw-mat-forms&action=edit&form_id='.$form_id.'&error=1'));
      exit;
    }

    if (!ovw_mat_get_active_form()) {
      ovw_mat_set_active_form($id);
    }

    wp_redirect(admin_url('admin.php?page=ovw-mat-forms&action=edit&form_id='.$id.'&saved=1'));
    exit;
  }

  public function set_active_form() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_set_active_' . $id);
    ovw_mat_set_active_form($id);
    wp_redirect(admin_url('admin.php?page=ovw-mat-forms'));
    exit;
  }

  public function duplicate_form() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_duplicate_form_' . $id);
    $new_id = ovw_mat_duplicate_form($id);
    wp_redirect(admin_url('admin.php?page=ovw-mat-forms&action=edit&form_id='.(int)$new_id));
    exit;
  }

  public function delete_form() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_delete_form_' . $id);
    ovw_mat_delete_form($id);
    wp_redirect(admin_url('admin.php?page=ovw-mat-forms'));
    exit;
  }

  public function export_form() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_export_form_' . $id);
    $export = ovw_mat_export_form($id);
    if (!$export) wp_die('Form not found');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="biodata-form-'.$id.'.json"');
    echo wp_json_encode($export);
    exit;
  }




  private function list_page($status) {
    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $form_id = (int)($_GET['form_id'] ?? 0);

    $where = "status=%s";
    $args = [$status];
    if ($form_id > 0) { $where .= " AND form_id=%d"; $args[] = $form_id; }
    $sql = "SELECT id, biodata_no, user_id, biodata_type, marital_status, age, present_address, created_at, was_approved
            FROM $table
            WHERE $where
            ORDER BY id DESC
            LIMIT 200";
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
    ?>
    <div class="wrap">
      <h1><?php echo esc_html(ucfirst($status)); ?> Biodata</h1>
      <?php if ($status === 'approved'): ?>
        <p>
          <?php $bulk_nonce = wp_create_nonce('ovw_mat_reapprove_all'); ?>
          <a class="button" href="<?php echo esc_url(admin_url('admin-post.php?action=ovw_mat_reapprove_all&_wpnonce='.$bulk_nonce.($form_id ? '&form_id='.(int)$form_id : ''))); ?>" onclick="return confirm('Send all approved biodata to re-approval?');">Request Re-approval (All)</a>
        </p>
      <?php endif; ?>

      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Biodata No</th>
            <th>User ID</th>
            <th>Type</th>
            <th>Marital</th>
            <th>Age</th>
            <th>Address</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8">No records.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td>
                <strong><?php echo esc_html($r['biodata_no']); ?></strong>
                <?php if ($status === 'pending' && !empty($r['was_approved'])): ?>
                  <span class="ovw-badge ovw-badge-warning">Resubmitted</span>
                <?php endif; ?>
              </td>
              <td><?php echo (int)$r['user_id']; ?></td>
              <td><?php echo esc_html($r['biodata_type']); ?></td>
              <td><?php echo esc_html($r['marital_status']); ?></td>
              <td><?php echo esc_html($r['age']); ?></td>
              <td><?php echo esc_html($r['present_address']); ?></td>
              <td>
                <a href="#" class="button ovw-mat-open" data-id="<?php echo (int)$r['id']; ?>">View</a>

                <?php if ($status === 'pending' || $status === 'rejected'): ?>
                  <?php $approve_nonce = wp_create_nonce('ovw_mat_approve_' . (int)$r['id']); ?>
                  <?php $reject_nonce  = wp_create_nonce('ovw_mat_reject_' . (int)$r['id']); ?>
                  <a class="button button-primary" href="<?php echo esc_url(admin_url('admin-post.php?action=ovw_mat_approve&id='.(int)$r['id'].'&_wpnonce='.$approve_nonce)); ?>">Approve</a>
                  <?php if ($status === 'pending'): ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin-post.php?action=ovw_mat_reject&id='.(int)$r['id'].'&_wpnonce='.$reject_nonce)); ?>">Reject</a>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($status === 'approved'): ?>
                  <?php $reapprove_nonce = wp_create_nonce('ovw_mat_reapprove_' . (int)$r['id']); ?>
                  <a class="button" href="<?php echo esc_url(admin_url('admin-post.php?action=ovw_mat_reapprove&id='.(int)$r['id'].'&_wpnonce='.$reapprove_nonce)); ?>">Request Re-approval</a>
                <?php endif; ?>
                <?php $delete_nonce = wp_create_nonce('ovw_mat_delete_' . (int)$r['id']); ?>
                <a class="button button-link-delete" href="<?php echo esc_url(admin_url('admin-post.php?action=ovw_mat_delete&id='.(int)$r['id'].'&_wpnonce='.$delete_nonce)); ?>" onclick="return confirm('Delete this biodata permanently?');">Delete</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div id="ovw-mat-modal" class="ovw-mat-modal" style="display:none;">
        <div class="ovw-mat-modal-inner">
          <div class="ovw-mat-modal-head">
            <strong>Biodata Details</strong>
            <button class="button" id="ovw-mat-close">Close</button>
          </div>
          <div id="ovw-mat-modal-body">Loading…</div>
        </div>
      </div>
    </div>
    <?php
  }

  public function page_pending() { $this->list_page('pending'); }
  public function page_approved() { $this->list_page('approved'); }
  public function page_rejected() { $this->list_page('rejected'); }

  public function page_settings() {
    if (!current_user_can('manage_options')) return;
    $settings = ovw_mat_dashboard_settings();
    $email_settings = ovw_mat_email_settings();
    $tab = sanitize_text_field($_GET['tab'] ?? 'pages');
    $auto_pages = ovw_mat_get_auto_pages();
    ?>
    <div class="wrap">
      <h1>Settings</h1>
      <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-settings&tab=pages')); ?>" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>">Page Settings</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ovw-mat-settings&tab=email')); ?>" class="nav-tab <?php echo $tab === 'email' ? 'nav-tab-active' : ''; ?>">Email Settings</a>
      </h2>

      <?php if ($tab === 'pages'): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="ovw_mat_save_dashboard_settings">
          <?php wp_nonce_field('ovw_mat_dashboard_settings', 'ovw_mat_dashboard_nonce'); ?>
          <table class="form-table">
            <tr>
              <th scope="row"><label for="edit_page_id">Edit Biodata Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'edit_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['edit_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="delete_page_id">Delete Biodata Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'delete_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['delete_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="shortlist_page_id">Shortlist Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'shortlist_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['shortlist_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="ignore_page_id">Ignore List Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'ignore_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['ignore_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="purchased_page_id">My Purchased Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'purchased_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['purchased_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="support_page_id">Support & Report Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'support_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['support_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="settings_page_id">Settings Page</label></th>
              <td><?php wp_dropdown_pages(['name' => 'settings_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['settings_page_id']]); ?></td>
            </tr>
            <tr>
              <th scope="row"><label for="add_biodata_page_id">Add Biodata Page (optional)</label></th>
              <td><?php wp_dropdown_pages(['name' => 'add_biodata_page_id', 'show_option_none' => '— Select —', 'selected' => $settings['add_biodata_page_id']]); ?></td>
            </tr>
          </table>
          <?php submit_button('Save Settings'); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
          <input type="hidden" name="action" value="ovw_mat_autoset_pages">
          <?php wp_nonce_field('ovw_mat_autoset_pages', 'ovw_mat_autoset_nonce'); ?>
          <?php submit_button('Auto Create Pages', 'secondary'); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:6px;">
          <input type="hidden" name="action" value="ovw_mat_reset_settings">
          <?php wp_nonce_field('ovw_mat_reset_settings', 'ovw_mat_reset_nonce'); ?>
          <?php submit_button('Reset Page Settings', 'delete'); ?>
        </form>

        <?php if (!empty($auto_pages)): ?>
          <h2>Auto-Created Pages</h2>
          <table class="widefat striped">
            <thead>
              <tr>
                <th>Page</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auto_pages as $key => $id): ?>
                <?php $title = get_the_title((int)$id); ?>
                <tr>
                  <td><?php echo esc_html($title ?: ('Page ID ' . (int)$id)); ?></td>
                  <td><a href="<?php echo esc_url(get_edit_post_link((int)$id)); ?>">Edit</a> | <a href="<?php echo esc_url(get_permalink((int)$id)); ?>" target="_blank" rel="noopener">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php else: ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="ovw_mat_save_email_settings">
          <?php wp_nonce_field('ovw_mat_email_settings', 'ovw_mat_email_nonce'); ?>
          <table class="form-table">
            <tr>
              <th scope="row"><label for="shortlist_subject">Shortlist Email Subject</label></th>
              <td><input type="text" name="shortlist_subject" class="regular-text" value="<?php echo esc_attr($email_settings['shortlist_subject']); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="shortlist_body">Shortlist Email Body</label></th>
              <td>
                <textarea name="shortlist_body" class="large-text" rows="6"><?php echo esc_textarea($email_settings['shortlist_body']); ?></textarea>
                <p class="description">You can use {user_name}.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="ignored_subject">Ignored Email Subject</label></th>
              <td><input type="text" name="ignored_subject" class="regular-text" value="<?php echo esc_attr($email_settings['ignored_subject']); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="ignored_body">Ignored Email Body</label></th>
              <td>
                <textarea name="ignored_body" class="large-text" rows="6"><?php echo esc_textarea($email_settings['ignored_body']); ?></textarea>
                <p class="description">You can use {user_name}.</p>
              </td>
            </tr>
          </table>
          <?php submit_button('Save Email Settings'); ?>
        </form>
      <?php endif; ?>
    </div>
    <?php
  }

  public function save_dashboard_settings() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_dashboard_settings', 'ovw_mat_dashboard_nonce');
    ovw_mat_update_dashboard_settings($_POST);
    wp_redirect(admin_url('admin.php?page=ovw-mat-settings&tab=pages&saved=1'));
    exit;
  }

  public function save_email_settings() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_email_settings', 'ovw_mat_email_nonce');
    ovw_mat_update_email_settings($_POST);
    wp_redirect(admin_url('admin.php?page=ovw-mat-settings&tab=email&saved=1'));
    exit;
  }

  public function autoset_pages() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_autoset_pages', 'ovw_mat_autoset_nonce');

    $pages = [
      'dashboard' => ['title' => 'Matrimonial Dashboard', 'content' => '[user_dashboard_ui]'],
      'shortlist' => ['title' => 'Matrimonial Short List', 'content' => '<p>Content coming soon.</p>'],
      'ignore' => ['title' => 'Matrimonial Ignore List', 'content' => '<p>Content coming soon.</p>'],
      'support' => ['title' => 'Matrimonial Support & Report', 'content' => '<p>Content coming soon.</p>'],
      'settings' => ['title' => 'Matrimonial User Settings', 'content' => '<p>Content coming soon.</p>'],
      'delete' => ['title' => 'Matrimonial Delete Biodata', 'content' => '<p>Content coming soon.</p>'],
      'purchased' => ['title' => 'Matrimonial My Purchased', 'content' => '<p>Content coming soon.</p>'],
      'add_biodata' => ['title' => 'Matrimonial Add Biodata', 'content' => '<p>Content coming soon.</p>'],
    ];

    $created = [];
    foreach ($pages as $key => $page) {
      $id = wp_insert_post([
        'post_title' => $page['title'],
        'post_content' => $page['content'],
        'post_status' => 'publish',
        'post_type' => 'page'
      ], true);
      if (!is_wp_error($id)) {
        $created[$key] = (int)$id;
        update_post_meta((int)$id, '_ovw_mat_auto_page', 1);
        update_post_meta((int)$id, '_ovw_mat_auto_key', sanitize_text_field($key));
      }
    }

    $settings = ovw_mat_dashboard_settings();
    ovw_mat_update_dashboard_settings([
      'edit_page_id' => $settings['edit_page_id'],
      'delete_page_id' => $created['delete'] ?? 0,
      'shortlist_page_id' => $created['shortlist'] ?? 0,
      'ignore_page_id' => $created['ignore'] ?? 0,
      'purchased_page_id' => $created['purchased'] ?? 0,
      'support_page_id' => $created['support'] ?? 0,
      'settings_page_id' => $created['settings'] ?? 0,
      'add_biodata_page_id' => $created['add_biodata'] ?? 0,
    ]);

    ovw_mat_set_auto_pages($created);
    wp_redirect(admin_url('admin.php?page=ovw-mat-settings&tab=pages&autoset=1'));
    exit;
  }

  public function reset_settings() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_reset_settings', 'ovw_mat_reset_nonce');

    $pages = ovw_mat_get_auto_pages();
    if ($pages) {
      foreach ($pages as $id) {
        wp_trash_post((int)$id);
      }
    }
    ovw_mat_set_auto_pages([]);
    ovw_mat_update_dashboard_settings([]);
    wp_redirect(admin_url('admin.php?page=ovw-mat-settings&tab=pages&reset=1'));
    exit;
  }

  public function post_states($states, $post) {
    if (!empty($post->ID) && get_post_meta($post->ID, '_ovw_mat_auto_page', true)) {
      $states['ovw_mat_auto'] = 'Matrimonial (Auto)';
    }
    return $states;
  }

  public function approve() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_approve_' . $id);

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $wpdb->update($table, [
      'status' => 'approved',
      'was_approved' => 1,
      'approved_at' => current_time('mysql')
    ], ['id' => $id]);

    wp_redirect(admin_url('admin.php?page=ovw-mat-pending&approved=1'));
    exit;
  }

  public function reject() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_reject_' . $id);

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $wpdb->update($table, ['status' => 'rejected'], ['id' => $id]);

    wp_redirect(admin_url('admin.php?page=ovw-mat-pending&rejected=1'));
    exit;
  }

  public function reapprove() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_reapprove_' . $id);

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $wpdb->update($table, ['status' => 'pending', 'was_approved' => 1], ['id' => $id]);

    wp_redirect(admin_url('admin.php?page=ovw-mat-approved&reapprove=1'));
    exit;
  }

  public function reapprove_all() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    check_admin_referer('ovw_mat_reapprove_all');

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $form_id = (int)($_GET['form_id'] ?? 0);

    if ($form_id > 0) {
      $wpdb->query($wpdb->prepare(
        "UPDATE $table SET status='pending', was_approved=1 WHERE status='approved' AND form_id=%d",
        $form_id
      ));
      $redirect = admin_url('admin.php?page=ovw-mat-approved&reapprove_all=1&form_id='.(int)$form_id);
    } else {
      $wpdb->query("UPDATE $table SET status='pending', was_approved=1 WHERE status='approved'");
      $redirect = admin_url('admin.php?page=ovw-mat-approved&reapprove_all=1');
    }

    wp_redirect($redirect);
    exit;
  }

  public function delete() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    $id = (int)($_GET['id'] ?? 0);
    check_admin_referer('ovw_mat_delete_' . $id);

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $wpdb->delete($table, ['id' => $id]);

    wp_redirect(admin_url('admin.php?page=ovw-mat-pending&deleted=1'));
    exit;
  }

  public function ajax_admin_entry() {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'No permission'], 403);
    }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
      wp_send_json_error(['message' => 'Missing id'], 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, biodata_no, status, payload_json, created_at, form_id FROM $table WHERE id=%d LIMIT 1",
      $id
    ), ARRAY_A);

    if (!$row) {
      wp_send_json_error(['message' => 'Not found'], 404);
    }

    $payload = json_decode($row['payload_json'], true);
    if (!is_array($payload)) $payload = [];

    wp_send_json_success([
      'id' => (int)$row['id'],
      'biodata_no' => $row['biodata_no'],
      'status' => $row['status'],
      'created_at' => $row['created_at'],
      'payload' => $payload,
      'schema' => ovw_mat_get_schema((int)($row['form_id'] ?? 0))
    ]);
  }
}
