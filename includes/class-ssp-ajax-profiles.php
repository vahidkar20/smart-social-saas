<?php
/**
 * SSP AJAX Profiles Trait - Profile/Business workspace CRUD
 */
trait SSP_AjaxProfiles {

    public function handle_get_profiles() {
        $user_id = $this->ajax_require_auth();
        $profiles = $this->get_user_items($user_id, 'profiles');
        if (empty($profiles)) {
            $profiles = [['id' => 1, 'name' => 'پیش‌فرض', 'color' => '#4F46E5', 'is_active' => true, 'created_at' => current_time('mysql')]];
            $this->set_user_items($user_id, 'profiles', $profiles);
        }
        wp_send_json_success(['profiles' => $profiles]);
    }

    public function handle_add_profile() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'ساخت پروفایل فقط در پلن Pro موجود است']);
        $profiles = $this->get_user_items($user_id, 'profiles');
        if (empty($profiles)) {
            $profiles = [['id' => 1, 'name' => 'پیش‌فرض', 'color' => '#4F46E5', 'is_active' => true, 'created_at' => current_time('mysql')]];
        }
        if (count($profiles) >= 20) wp_send_json_error(['message' => 'حداکثر ۲۰ پروفایل می‌توانید بسازید']);
        $id = $this->next_id($profiles);
        $colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];
        $profiles[] = [
            'id' => $id,
            'name' => sanitize_text_field($_POST['name'] ?? 'پروفایل جدید'),
            'color' => sanitize_text_field($_POST['color'] ?? $colors[count($profiles) % count($colors)]),
            'is_active' => false,
            'created_at' => current_time('mysql'),
        ];
        $this->set_user_items($user_id, 'profiles', $profiles);
        wp_send_json_success(['message' => 'پروفایل ایجاد شد!', 'id' => $id]);
    }

    public function handle_update_profile() {
        $user_id = $this->ajax_require_auth();
        $profiles = $this->get_user_items($user_id, 'profiles');
        $id = intval($_POST['profile_id']);
        foreach ($profiles as &$p) {
            if ((int)$p['id'] === $id) {
                if (isset($_POST['name'])) $p['name'] = sanitize_text_field($_POST['name']);
                if (isset($_POST['color'])) $p['color'] = sanitize_text_field($_POST['color']);
                break;
            }
        }
        unset($p);
        $this->set_user_items($user_id, 'profiles', $profiles);
        wp_send_json_success(['message' => 'پروفایل بروزرسانی شد']);
    }

    public function handle_switch_profile() {
        $user_id = $this->ajax_require_auth();
        $profiles = $this->get_user_items($user_id, 'profiles');
        $id = intval($_POST['profile_id']);
        foreach ($profiles as &$p) {
            $p['is_active'] = ((int)$p['id'] === $id);
        }
        unset($p);
        $this->set_user_items($user_id, 'profiles', $profiles);
        wp_send_json_success(['message' => 'پروفایل تغییر کرد']);
    }

    public function handle_delete_profile() {
        $user_id = $this->ajax_require_auth();
        $profiles = $this->get_user_items($user_id, 'profiles');
        $id = intval($_POST['profile_id']);
        if ($id === 1) wp_send_json_error(['message' => 'پروفایل پیش‌فرض قابل حذف نیست']);
        $was_active = false;
        foreach ($profiles as $p) {
            if ((int)$p['id'] === $id && !empty($p['is_active'])) $was_active = true;
        }
        $profiles = array_values(array_filter($profiles, function($p) use ($id) { return (int)$p['id'] !== $id; }));
        if ($was_active && !empty($profiles)) $profiles[0]['is_active'] = true;
        $this->set_user_items($user_id, 'profiles', $profiles);
        // Remove associated items
        $this->remove_items_by_profile($user_id, $id);
        wp_send_json_success(['message' => 'پروفایل حذف شد']);
    }

    private function get_active_profile_id($user_id) {
        $profiles = $this->get_user_items($user_id, 'profiles');
        if (empty($profiles)) {
            $profiles = [['id' => 1, 'name' => 'پیش‌فرض', 'color' => '#4F46E5', 'is_active' => true, 'created_at' => current_time('mysql')]];
            $this->set_user_items($user_id, 'profiles', $profiles);
        }
        foreach ($profiles as $p) {
            if (!empty($p['is_active'])) return (int)$p['id'];
        }
        return 1;
    }

    private function get_profile_items($user_id, $key, $profile_id = null) {
        $items = $this->get_user_items($user_id, $key);
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        $filtered = array_filter($items, function($item) use ($profile_id) {
            return (int)($item['profile_id'] ?? 1) === $profile_id;
        });
        return array_values($filtered);
    }

    private function set_profile_item($user_id, $key, $item, $profile_id = null) {
        $items = $this->get_user_items($user_id, $key);
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        $item['profile_id'] = $profile_id;
        $id = $item['id'] ?? 0;
        $found = false;
        foreach ($items as &$existing) {
            if ((int)($existing['id'] ?? 0) === $id && (int)($existing['profile_id'] ?? 1) === $profile_id) {
                $existing = $item;
                $found = true;
                break;
            }
        }
        unset($existing);
        if (!$found) $items[] = $item;
        $this->set_user_items($user_id, $key, $items);
    }

    private function delete_profile_item($user_id, $key, $item_id, $profile_id = null) {
        $items = $this->get_user_items($user_id, $key);
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        $items = array_values(array_filter($items, function($item) use ($item_id, $profile_id) {
            return !((int)($item['id'] ?? 0) === $item_id && (int)($item['profile_id'] ?? 1) === $profile_id);
        }));
        $this->set_user_items($user_id, $key, $items);
    }

    private function remove_items_by_profile($user_id, $profile_id) {
        $keys = ['messengers', 'wp_sites', 'rss_feeds', 'schedules', 'triggers', 'product_prompt_templates', 'template_items', 'distributions'];
        foreach ($keys as $key) {
            $items = $this->get_user_items($user_id, $key);
            $items = array_values(array_filter($items, function($item) use ($profile_id) {
                return (int)($item['profile_id'] ?? 1) !== $profile_id;
            }));
            $this->set_user_items($user_id, $key, $items);
        }
    }

    private function ensure_profile_fields($user_id) {
        $keys = ['messengers', 'wp_sites', 'rss_feeds', 'schedules', 'triggers', 'template_items'];
        $changed = false;
        foreach ($keys as $key) {
            $items = $this->get_user_items($user_id, $key);
            $modified = false;
            foreach ($items as &$item) {
                if (!isset($item['profile_id'])) {
                    $item['profile_id'] = 1;
                    $modified = true;
                }
            }
            unset($item);
            if ($modified) {
                $this->set_user_items($user_id, $key, $items);
                $changed = true;
            }
        }
        return $changed;
    }
}
