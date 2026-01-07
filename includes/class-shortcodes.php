<?php
if (!defined('ABSPATH')) exit;

class OVW_MAT_Shortcodes {

  public function __construct() {
    add_shortcode('ovw_matrimonial_create', [$this, 'create']);
    add_shortcode('ovw_matrimonial_search', [$this, 'search']);
    add_shortcode('ovw_matrimonial_view', [$this, 'view']);
    add_shortcode('user_dashboard_ui', [$this, 'dashboard']);
    add_action('admin_post_ovw_mat_delete_my_biodata', [$this, 'delete_my_biodata']);
  }

  private function enqueue_common_assets() {
    wp_enqueue_style('ovw-mat-form', OVW_MAT_URL . 'assets/css/form.css', [], OVW_MAT_VER);
    wp_enqueue_script('ovw-mat-form', OVW_MAT_URL . 'assets/js/form.js', [], OVW_MAT_VER, true);
    wp_localize_script('ovw-mat-form', 'OVW_MAT', [
      'rest' => esc_url_raw(rest_url('ovw-matrimonial/v1')),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);
  }

  public function create($atts = []) {
    if (!is_user_logged_in()) {
      return '<p><strong>Please login to create your biodata.</strong></p>';
    }

    $this->enqueue_common_assets();
    $atts = shortcode_atts(['form_id' => 0], $atts);
    $form_id = (int)($atts['form_id'] ?? 0);

    return '
      <div class="ovw-form-wrap" id="ovwBioApp" data-form-id="'.esc_attr($form_id).'">
        <div class="ovw-left">
          <div class="ovw-left-title">Steps</div>
          <div class="ovw-nav" id="ovwBioNav"></div>
        </div>
        <div class="ovw-right">
          <div class="ovw-right-head">
            <div class="ovw-right-title" id="ovwBioSectionTitle">Loading…</div>
            <div class="ovw-message" id="ovwBioMsg" style="display:none;"></div>
          </div>
          <form class="ovw-form" id="ovwBioForm">
            <div id="ovwBioFields"></div>
          </form>
        </div>
      </div>
    ';
  }

  public function search($atts = []) {
    $this->enqueue_common_assets();
    $atts = shortcode_atts(['form_id' => 0], $atts);
    $form_id = (int)($atts['form_id'] ?? 0);

    // Search bar UI like your screenshot
    return '
      <div class="ovw-top-search" id="ovwTopSearch" data-form-id="'.esc_attr($form_id).'">
        <div class="ovw-top-search-box">
          <div class="ovw-ts-field">
            <div class="ovw-ts-label">I\'m looking for</div>
            <select class="ovw-ts-select" id="ovw_filter_type">
              <option value="">All</option>
            </select>
          </div>

          <div class="ovw-ts-field">
            <div class="ovw-ts-label">Marital Status</div>
            <select class="ovw-ts-select" id="ovw_filter_marital">
              <option value="">All</option>
            </select>
          </div>

          <div class="ovw-ts-field">
            <div class="ovw-ts-label">Present Address</div>
            <select class="ovw-ts-select" id="ovw_filter_address">
              <option value="">Select an address</option>
            </select>
          </div>

          <div class="ovw-ts-btnwrap">
            <button type="button" class="ovw-ts-btn" id="ovw_search_btn">
              <span class="ovw-ts-icon">🔎</span> Search Biodata
            </button>
          </div>
        </div>
      </div>

      <div class="ovw-search-meta">
        <div class="ovw-muted" id="ovw_search_count"></div>
      </div>

      <div class="ovw-grid" id="ovw_search_results"></div>

      <div class="ovw-search-actions">
        <button type="button" class="ovw-load" id="ovw_load_more" style="display:none;">Load More Biodata</button>
      </div>
    ';
  }

  public function view() {
    $this->enqueue_common_assets();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) return '<p>No biodata selected.</p>';

    return '
      <div class="ovw-view-wrap" id="ovwView" data-id="'.esc_attr($id).'">
        <div class="ovw-view-left">
          <div class="ovw-view-no" id="ovwViewNo">Loading…</div>
          <div class="ovw-muted">Biodata details</div>
        </div>
        <div class="ovw-view-right" id="ovwViewRight"></div>
      </div>
    ';
  }

  public function dashboard() {
    if (!is_user_logged_in()) {
      return '<p><strong>Please login to view your dashboard.</strong></p>';
    }

    wp_enqueue_style('ovw-mat-dashboard', OVW_MAT_URL . 'assets/css/dashboard.css', [], OVW_MAT_VER);
    wp_enqueue_script('ovw-mat-dashboard', OVW_MAT_URL . 'assets/js/dashboard.js', [], OVW_MAT_VER, true);
    wp_enqueue_style('ovw-mat-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
    wp_localize_script('ovw-mat-dashboard', 'OVW_MAT_DASH', [
      'rest' => esc_url_raw(rest_url('ovw-matrimonial/v1')),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);

    $settings = ovw_mat_dashboard_settings();
    $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';
    $base_url = get_permalink();
    $logout_url = wp_logout_url(home_url());

    $active_form = ovw_mat_get_active_form();
    $form_id = $active_form['id'] ?? 0;
    $entry = ovw_mat_get_user_biodata(get_current_user_id(), $form_id);
    $photo_url = ovw_mat_get_profile_photo_url(get_current_user_id(), $form_id);

    $status = $entry['status'] ?? '';
    $was_approved = (int)($entry['was_approved'] ?? 0);
    $status_label = 'NOT CREATED';
    if ($status === 'draft') $status_label = 'NOT SUBMITTED';
    elseif ($status === 'pending' && $was_approved) $status_label = 'RESUBMITTED';
    elseif ($status === 'pending') $status_label = 'PENDING';
    elseif ($status === 'approved') $status_label = 'APPROVED';
    elseif ($status === 'rejected') $status_label = 'REJECTED';

    $create_label = $entry ? ($status === 'draft' ? 'Continue Biodata' : 'Edit Biodata') : 'Create Biodata';

    $pmpro_link = function_exists('pmpro_url') ? pmpro_url('account') : ovw_mat_membership_page_url();

    $delete_msg = '';
    if (!empty($_GET['deleted'])) {
      $delete_msg = '<div class="od-notice success">Your biodata has been deleted.</div>';
    }

    ob_start();
    ?>
    <div class="od-dashboard-wrapper">
      <div id="odSidebar" class="od-sidebar">
        <div id="odSidebarToggle" class="od-toggle-btn">
          <i class="fa-solid fa-chevron-right"></i>
        </div>

        <div class="od-sidebar-scroll-area">
          <div class="od-user-profile">
            <label class="od-avatar-picker">
              <input type="file" id="odAvatarInput" accept="image/*">
              <span class="od-avatar">
                <?php if (!empty($photo_url)): ?>
                  <img src="<?php echo esc_url($photo_url); ?>" alt="Profile">
                <?php else: ?>
                  <i class="fa-solid fa-user"></i>
                <?php endif; ?>
              </span>
              <span class="od-avatar-help">Change Photo</span>
            </label>
            <h3>Biodata Status</h3>
            <span class="od-status-badge"><?php echo esc_html($status_label); ?></span>
            <br>
            <a href="<?php echo esc_url(add_query_arg('view', 'edit-biodata', $base_url)); ?>" class="od-create-btn-sm"><?php echo esc_html($create_label); ?></a>
          </div>
          <ul class="od-menu">
            <li>
              <a href="<?php echo esc_url($base_url); ?>" class="<?php echo ($current_view === 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Dashboard
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'edit-biodata', $base_url)); ?>" class="<?php echo ($current_view === 'edit-biodata') ? 'active' : ''; ?>">
                <i class="fa-regular fa-pen-to-square"></i> Edit Biodata
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'shortlist', $base_url)); ?>" class="<?php echo ($current_view === 'shortlist') ? 'active' : ''; ?>">
                <i class="fa-regular fa-heart"></i> Short list
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'ignore-list', $base_url)); ?>" class="<?php echo ($current_view === 'ignore-list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-heart-crack"></i> Ignore list
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'my-purchased', $base_url)); ?>" class="<?php echo ($current_view === 'my-purchased') ? 'active' : ''; ?>">
                <i class="fa-solid fa-bag-shopping"></i> My purchased
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'support', $base_url)); ?>" class="<?php echo ($current_view === 'support') ? 'active' : ''; ?>">
                <i class="fa-regular fa-flag"></i> Support & Report
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'settings', $base_url)); ?>" class="<?php echo ($current_view === 'settings') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i> Settings
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url(add_query_arg('view', 'delete-biodata', $base_url)); ?>" class="<?php echo ($current_view === 'delete-biodata') ? 'active' : ''; ?>">
                <i class="fa-regular fa-trash-can"></i> Delete Biodata
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url($logout_url); ?>">
                <i class="fa-solid fa-right-from-bracket"></i> Log out
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="od-main-content">
        <?php if ($current_view === 'edit-biodata'): ?>
          <div class="od-elementor-content">
            <?php
            if (!empty($settings['edit_page_id'])) {
              echo $this->render_page_or_placeholder($settings['edit_page_id'], 'Edit Biodata');
            }
            ?>
            <?php echo do_shortcode('[ovw_matrimonial_create form_id="'.(int)$form_id.'"]'); ?>
          </div>
        <?php elseif ($current_view === 'delete-biodata'): ?>
          <div class="od-elementor-content od-delete-wrap">
            <?php echo $delete_msg; ?>
            <?php
            if (!empty($settings['delete_page_id'])) {
              echo $this->render_page_or_placeholder($settings['delete_page_id'], 'Delete Biodata');
            } else {
              echo '<p>Content coming soon.</p>';
            }
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete your biodata permanently?');">
              <input type="hidden" name="action" value="ovw_mat_delete_my_biodata">
              <?php wp_nonce_field('ovw_mat_delete_my_biodata', 'ovw_mat_delete_nonce'); ?>
              <button type="submit" class="od-danger-btn">Delete My Biodata</button>
            </form>
          </div>
        <?php elseif ($current_view === 'shortlist'): ?>
          <div class="od-elementor-content">
            <?php echo $this->render_page_or_placeholder($settings['shortlist_page_id'], 'Short List'); ?>
          </div>
        <?php elseif ($current_view === 'ignore-list'): ?>
          <div class="od-elementor-content">
            <?php echo $this->render_page_or_placeholder($settings['ignore_page_id'], 'Ignore List'); ?>
          </div>
        <?php elseif ($current_view === 'my-purchased'): ?>
          <div class="od-elementor-content">
            <?php
            if (!empty($settings['purchased_page_id'])) {
              echo $this->render_page_or_placeholder($settings['purchased_page_id'], 'My Purchased');
            } else {
              echo '<p>Your purchases are managed by Paid Memberships Pro.</p>';
              echo '<p><a class="od-link" href="'.esc_url($pmpro_link).'">View your membership account</a></p>';
            }
            ?>
          </div>
        <?php elseif ($current_view === 'support'): ?>
          <div class="od-elementor-content">
            <?php echo $this->render_page_or_placeholder($settings['support_page_id'], 'Support & Report'); ?>
          </div>
        <?php elseif ($current_view === 'settings'): ?>
          <div class="od-elementor-content">
            <?php echo $this->render_page_or_placeholder($settings['settings_page_id'], 'Settings'); ?>
          </div>
        <?php else: ?>
          <?php if (!$entry || ($entry && ($entry['status'] ?? '') === 'draft')): ?>
            <div class="od-top-action">
              <a href="<?php echo esc_url(add_query_arg('view', 'edit-biodata', $base_url)); ?>" class="od-big-btn">Create Your Biodata</a>
              <p class="od-top-note">NusfahDeen is completely free to create biodata.</p>
              <a href="#" class="od-tutorial-link"><i class="fa-brands fa-youtube"></i> How to create biodata</a>
            </div>
          <?php endif; ?>
          <div class="od-grid">
            <div class="od-card purple-bg">
              <div class="od-count">0</div>
              <div class="od-card-title">Connections available</div>
              <div class="od-desc">1 connection is required to view contact details of each biodata</div>
              <a href="<?php echo esc_url($pmpro_link); ?>" class="od-buy-btn">Buy more connection</a>
            </div>
            <div class="od-card od-visit-stats">
              <div class="od-count">0</div>
              <div class="od-card-title">Number of Biodata Visits</div>
              <div class="od-desc">Number of times your biodata has been visited.</div>
              <div class="od-filter-row">
                <div class="od-stat-box"><span class="od-stat-badge">Last 30 Days</span><span class="od-stat-num">0</span></div>
                <div class="od-stat-box"><span class="od-stat-badge">Last 7 Days</span><span class="od-stat-num">0</span></div>
                <div class="od-stat-box"><span class="od-stat-badge">Today</span><span class="od-stat-num">0</span></div>
              </div>
            </div>
            <div class="od-card">
              <div class="od-count">0</div>
              <div class="od-card-title">Your biodata has been shortlisted</div>
              <div class="od-desc">Those number of people shortlisted your biodata</div>
            </div>
            <div class="od-card od-card-horizontal">
              <div class="od-icon-wrapper"><i class="fa-regular fa-heart od-icon-heart"></i></div>
              <div class="od-content-right">
                <div class="od-count">0</div>
                <div class="od-card-title purple-text">Short List</div>
                <div class="od-desc">All your short listed biodatas</div>
              </div>
            </div>
            <div class="od-card od-card-horizontal">
              <div class="od-icon-wrapper"><i class="fa-solid fa-heart-circle-xmark od-icon-ignore"></i></div>
              <div class="od-content-right">
                <div class="od-count">0</div>
                <div class="od-card-title purple-text">Ignore List</div>
                <div class="od-desc">All your Ignore listed biodatas</div>
              </div>
            </div>
            <div class="od-card od-card-horizontal">
              <div class="od-icon-wrapper"><i class="fa-solid fa-bag-shopping od-icon-bag"></i></div>
              <div class="od-content-right">
                <div class="od-count">0</div>
                <div class="od-card-title purple-text">My Purchased</div>
                <div class="od-desc">All your purchased history</div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

  private function render_page_or_placeholder($page_id, $title) {
    $page_id = (int)$page_id;
    if ($page_id > 0) {
      if (class_exists('\\Elementor\\Plugin')) {
        return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($page_id);
      }
      $post = get_post($page_id);
      return $post ? apply_filters('the_content', $post->post_content) : '<p>Page not found.</p>';
    }
    return '<h2>' . esc_html($title) . '</h2><p>Content coming soon.</p>';
  }

  public function delete_my_biodata() {
    if (!is_user_logged_in()) wp_die('Please login.');
    check_admin_referer('ovw_mat_delete_my_biodata', 'ovw_mat_delete_nonce');

    $user_id = get_current_user_id();
    $active_form = ovw_mat_get_active_form();
    $form_id = $active_form['id'] ?? 0;

    global $wpdb;
    $table = $wpdb->prefix . 'ovw_matrimonial_entries';
    if ($form_id > 0) {
      $wpdb->delete($table, ['user_id' => $user_id, 'form_id' => $form_id]);
    } else {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE user_id=%d AND (form_id IS NULL OR form_id=0)",
        $user_id
      ));
    }

    $back = wp_get_referer();
    if (!$back) $back = get_permalink();
    wp_safe_redirect(add_query_arg('deleted', '1', $back));
    exit;
  }
}
