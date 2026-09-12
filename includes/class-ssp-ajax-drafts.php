<?php
/**
 * SSP AJAX Drafts Trait - Drafts CRUD AJAX
 */
trait SSP_AjaxDrafts {

    public function handle_save_draft() {
        $user_id = $this->ajax_require_auth();
        $drafts = $this->get_user_drafts($user_id);
        if (count($drafts) >= SSP_MAX_DRAFTS) wp_send_json_error(['message' => 'حداکثر ' . SSP_MAX_DRAFTS . ' پیش‌نویس می‌توانید داشته باشید']);
        $id = $this->next_id($drafts);
        $drafts[] = [
            'id' => $id, 'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => sanitize_textarea_field($_POST['content'] ?? ''),
            'hashtags' => sanitize_text_field($_POST['hashtags'] ?? ''),
            'meta_description' => sanitize_textarea_field($_POST['meta_description'] ?? ''),
            'draft_type' => sanitize_text_field($_POST['draft_type'] ?? ''),
            'status' => 'draft', 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
        ];
        $this->set_user_drafts($user_id, $drafts);
        wp_send_json_success(['message' => 'پیش‌نویس ذخیره شد!', 'id' => $id]);
    }

    public function handle_get_drafts() {
        $user_id = $this->ajax_require_auth();
        wp_send_json_success(['drafts' => $this->get_user_drafts($user_id)]);
    }

    public function handle_delete_draft() {
        $user_id = $this->ajax_require_auth();
        $drafts = $this->get_user_drafts($user_id);
        $id = intval($_POST['draft_id'] ?? $_POST['id'] ?? 0);
        $drafts = array_values(array_filter($drafts, function($d) use ($id) { return (int)$d['id'] !== $id; }));
        $this->set_user_drafts($user_id, $drafts);
        wp_send_json_success(['message' => 'پیش‌نویس حذف شد']);
    }

    public function handle_update_draft() {
        $user_id = $this->ajax_require_auth();
        $drafts = $this->get_user_drafts($user_id);
        $id = intval($_POST['draft_id'] ?? $_POST['id'] ?? 0);
        foreach ($drafts as &$draft) {
            if ((int)$draft['id'] === $id) {
                $draft['title'] = sanitize_text_field($_POST['title'] ?? $draft['title']);
                $draft['content'] = sanitize_textarea_field($_POST['content'] ?? $draft['content']);
                $draft['hashtags'] = sanitize_text_field($_POST['hashtags'] ?? $draft['hashtags']);
                $draft['meta_description'] = sanitize_textarea_field($_POST['meta_description'] ?? $draft['meta_description']);
                $draft['updated_at'] = current_time('mysql');
                break;
            }
        }
        unset($draft);
        $this->set_user_drafts($user_id, $drafts);
        wp_send_json_success(['message' => 'پیش‌نویس بروزرسانی شد']);
    }
}
