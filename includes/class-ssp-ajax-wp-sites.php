<?php
/**
 * SSP AJAX WP Sites Trait - WordPress Sites CRUD AJAX
 */
trait SSP_AjaxWpSites {

    public function handle_add_wp_site() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) === 'free') wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        $profile_id = $this->get_active_profile_id($user_id);
        $sites = $this->get_profile_wp_sites($user_id, $profile_id);
        if (count($sites) >= 10) wp_send_json_error(['message' => 'حداکثر ۱۰ سایت می‌توانید اضافه کنید']);
        $all_sites = $this->get_user_items($user_id, 'wp_sites');
        $id = $this->next_id($all_sites);
        $all_sites[] = [
            'id' => $id, 'profile_id' => $profile_id,
            'site_name' => sanitize_text_field($_POST['site_name'] ?? 'سایت جدید'),
            'site_url' => esc_url_raw($_POST['site_url'] ?? ''), 'username' => sanitize_text_field($_POST['username'] ?? ''),
            'app_password' => sanitize_text_field($_POST['app_password'] ?? ''), 'is_active' => intval($_POST['is_active'] ?? 1),
            'auto_publish' => intval($_POST['auto_publish'] ?? 1), 'post_type' => sanitize_text_field($_POST['post_type'] ?? 'post'),
            'categories' => sanitize_text_field($_POST['categories'] ?? ''), 'created_at' => current_time('mysql'),
        ];
        $this->set_user_items($user_id, 'wp_sites', $all_sites);
        wp_send_json_success(['message' => 'سایت با موفقیت اضافه شد!', 'id' => $id]);
    }

    public function handle_delete_wp_site() {
        $user_id = $this->ajax_require_auth();
        $sites = $this->get_user_wp_sites($user_id);
        $id = intval($_POST['site_id']);
        $sites = array_values(array_filter($sites, function($s) use ($id) { return (int)$s['id'] !== $id; }));
        $this->set_user_items($user_id, 'wp_sites', $sites);
        wp_send_json_success(['message' => 'سایت حذف شد']);
    }

    public function handle_update_wp_site() {
        $user_id = $this->ajax_require_auth();
        $sites = $this->get_user_wp_sites($user_id);
        $id = intval($_POST['site_id']);
        foreach ($sites as &$s) {
            if ((int)$s['id'] === $id) {
                $s['site_name'] = sanitize_text_field($_POST['site_name'] ?? '');
                $s['site_url'] = esc_url_raw($_POST['site_url'] ?? '');
                $s['username'] = sanitize_text_field($_POST['username'] ?? '');
                $new_pass = sanitize_text_field($_POST['app_password'] ?? '');
                if (!empty($new_pass)) $s['app_password'] = $new_pass;
                $s['is_active'] = intval($_POST['is_active'] ?? 1);
                $s['auto_publish'] = intval($_POST['auto_publish'] ?? 1);
                $s['post_type'] = sanitize_text_field($_POST['post_type'] ?? 'post');
                $s['categories'] = sanitize_text_field($_POST['categories'] ?? '');
                break;
            }
        }
        unset($s);
        $this->set_user_items($user_id, 'wp_sites', $sites);
        wp_send_json_success(['message' => 'تغییرات ذخیره شد']);
    }

    public function handle_test_wp_site() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = get_current_user_id();
        $site_id = intval($_POST['site_id'] ?? $_POST['id'] ?? 0);
        if ($site_id > 0) {
            $sites = $this->get_user_items($user_id, 'wp_sites');
            $site = null;
            foreach ($sites as $s) {
                if ((int)$s['id'] === $site_id) { $site = $s; break; }
            }
            if (!$site) wp_send_json_error(['message' => 'سایت یافت نشد']);
            $url = $site['site_url'];
            $user = $site['username'];
            $pass = $site['app_password'];
        } else {
            $url = esc_url_raw($_POST['site_url']);
            $user = sanitize_text_field($_POST['username']);
            $pass = sanitize_text_field($_POST['app_password']);
        }
        if (empty($url) || empty($user) || empty($pass)) wp_send_json_error(['message' => 'اطلاعات ناقص است']);
        $endpoint = rtrim($url, '/') . '/wp-json/wp/v2/users/me';
        $response = wp_remote_get($endpoint, ['headers' => ['Authorization' => 'Basic ' . base64_encode($user . ':' . $pass)]]);
        if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) wp_send_json_success(['message' => 'اتصال موفق!']);
        wp_send_json_error(['message' => 'خطای ' . $code]);
    }

    public function handle_fetch_wp_categories() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $url = esc_url_raw($_POST['site_url']);
        $user = sanitize_text_field($_POST['username']);
        $pass = sanitize_text_field($_POST['app_password']);
        $endpoint = rtrim($url, '/') . '/wp-json/wp/v2/categories?per_page=100';
        $response = wp_remote_get($endpoint, ['headers' => ['Authorization' => 'Basic ' . base64_encode($user . ':' . $pass)], 'timeout' => 15]);
        if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200 && is_array($body)) {
            $cats = array_map(function($c) { return ['id' => $c['id'], 'name' => $c['name']]; }, $body);
            wp_send_json_success(['categories' => $cats]);
        }
        wp_send_json_error(['message' => 'خطای ' . $code]);
    }
}
