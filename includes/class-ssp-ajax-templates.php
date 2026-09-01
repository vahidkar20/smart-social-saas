<?php
/**
 * SSP AJAX Templates Trait - Profile-scoped Templates CRUD
 */
trait SSP_AjaxTemplates {

    public function handle_save_template_item() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);

        $is_update = !empty($_POST['template_id']);

        if ($is_update) {
            $existing = $this->get_profile_items($user_id, 'template_items', $profile_id);
            $found = null;
            foreach ($existing as $e) {
                if ((int)$e['id'] === intval($_POST['template_id'])) { $found = $e; break; }
            }
            if (!$found) wp_send_json_error(['message' => 'قالب یافت نشد']);

            $item = [
                'id'          => $found['id'],
                'profile_id'  => $profile_id,
                'name'        => sanitize_text_field($_POST['name'] ?? $found['name']),
                'category'    => sanitize_text_field($_POST['category'] ?? $found['category']),
                'content'     => sanitize_textarea_field($_POST['content'] ?? $found['content']),
                'hashtags'    => sanitize_text_field($_POST['hashtags'] ?? $found['hashtags']),
                'signature'   => sanitize_text_field($_POST['signature'] ?? $found['signature']),
                'usage_count' => intval($found['usage_count'] ?? 0),
                'created_at'  => $found['created_at'] ?? current_time('mysql'),
            ];
        } else {
            $all_items = $this->get_user_items($user_id, 'template_items');
            $profile_items = $this->get_profile_items($user_id, 'template_items', $profile_id);
            if (count($profile_items) >= SSP_MAX_TEMPLATES) {
                wp_send_json_error(['message' => 'حداکثر ' . SSP_MAX_TEMPLATES . ' قالب می‌توانید داشته باشید']);
            }

            $item = [
                'id'          => $this->next_id($all_items),
                'name'        => sanitize_text_field($_POST['name'] ?? ''),
                'category'    => sanitize_text_field($_POST['category'] ?? 'general'),
                'content'     => sanitize_textarea_field($_POST['content'] ?? ''),
                'hashtags'    => sanitize_text_field($_POST['hashtags'] ?? ''),
                'signature'   => sanitize_text_field($_POST['signature'] ?? ''),
                'usage_count' => 0,
                'created_at'  => current_time('mysql'),
            ];
        }

        $this->set_profile_item($user_id, 'template_items', $item, $profile_id);
        wp_send_json_success(['message' => 'قالب ذخیره شد!', 'id' => $item['id']]);
    }

    public function handle_get_templates() {
        $user_id = $this->ajax_require_auth();
        $templates = $this->get_profile_items($user_id, 'template_items');
        wp_send_json_success(['templates' => $templates]);
    }

    public function handle_delete_template_item() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $item_id = intval($_POST['template_id']);
        $this->delete_profile_item($user_id, 'template_items', $item_id, $profile_id);
        wp_send_json_success(['message' => 'قالب حذف شد']);
    }

    public function handle_set_template_default() {
        $user_id = $this->ajax_require_auth();
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            $templates = $this->get_profile_items($user_id, 'template_items');
            $found = false;
            foreach ($templates as $t) {
                if ((int)$t['id'] === $template_id) { $found = true; break; }
            }
            if (!$found) wp_send_json_error(['message' => 'قالب یافت نشد']);
        }
        update_user_meta($user_id, 'ssp_default_template_id', $template_id);
        wp_send_json_success(['message' => 'قالب پیش‌فرض ذخیره شد']);
    }

    public function handle_increment_template_usage() {
        $user_id = $this->ajax_require_auth();
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id <= 0) wp_send_json_error(['message' => 'شناسه قالب نامعتبر']);

        $all_items = $this->get_user_items($user_id, 'template_items');
        $found = false;
        foreach ($all_items as &$item) {
            if ((int)$item['id'] === $template_id) {
                $item['usage_count'] = intval($item['usage_count'] ?? 0) + 1;
                $found = true;
                break;
            }
        }
        unset($item);
        if ($found) {
            $this->set_user_items($user_id, 'template_items', $all_items);
            wp_send_json_success(['message' => 'OK']);
        } else {
            wp_send_json_error(['message' => 'قالب یافت نشد']);
        }
    }
}
