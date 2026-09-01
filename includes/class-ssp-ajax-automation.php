<?php
/**
 * SSP AJAX Automation Trait - Schedules, triggers, bulk send, calendar
 */
trait SSP_AjaxAutomation {

    public function handle_add_schedule() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'زمان‌بندی فقط در پلن Pro موجود است']);
        $schedules = $this->get_user_items($user_id, 'schedules');
        $id = $this->next_id($schedules);
        $schedules[] = [
            'id' => $id, 'title' => sanitize_text_field($_POST['title'] ?? ''),
            'message' => sanitize_textarea_field($_POST['message'] ?? ''),
            'scheduled_at' => sanitize_text_field($_POST['scheduled_at'] ?? ''),
            'status' => 'pending', 'recurring' => sanitize_text_field($_POST['recurring'] ?? ''),
            'created_at' => current_time('mysql'),
        ];
        $this->set_user_items($user_id, 'schedules', $schedules);
        wp_send_json_success(['message' => 'زمان‌بندی ایجاد شد!', 'id' => $id]);
    }

    public function handle_delete_schedule() {
        $user_id = $this->ajax_require_auth();
        $schedules = $this->get_user_items($user_id, 'schedules');
        $id = intval($_POST['schedule_id']);
        $schedules = array_values(array_filter($schedules, function($s) use ($id) { return (int)$s['id'] !== $id; }));
        $this->set_user_items($user_id, 'schedules', $schedules);
        wp_send_json_success(['message' => 'زمان‌بندی حذف شد']);
    }

    public function handle_add_trigger() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'تریگرها فقط در پلن Pro موجود است']);
        $triggers = $this->get_user_items($user_id, 'triggers');
        $id = $this->next_id($triggers);
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $response = sanitize_textarea_field($_POST['response'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'telegram');
        $match_type = sanitize_text_field($_POST['match_type'] ?? 'exact');

        if (empty($keyword) || empty($response)) {
            wp_send_json_error(['message' => 'کلمه کلیدی و پاسخ الزامی است']);
        }

        $trigger = [
            'id' => $id, 'keyword' => $keyword, 'response' => $response,
            'platform' => $platform, 'match_type' => $match_type,
            'is_active' => 1, 'match_count' => 0, 'created_at' => current_time('mysql'),
        ];
        $triggers[] = $trigger;
        $this->set_user_items($user_id, 'triggers', $triggers);

        // Sync to bot auto_replies
        $this->sync_trigger_to_bot($user_id, $trigger, 'add');

        wp_send_json_success(['message' => 'تریگر ایجاد شد و به بات متصل شد!', 'id' => $id]);
    }

    public function handle_delete_trigger() {
        $user_id = $this->ajax_require_auth();
        $triggers = $this->get_user_items($user_id, 'triggers');
        $id = isset($_POST['trigger_id']) ? intval($_POST['trigger_id']) : 0;
        if ($id <= 0) wp_send_json_error(['message' => 'تریگر نامعتبر']);

        // Find trigger before deleting (to sync removal to bot)
        $deleted_trigger = null;
        foreach ($triggers as $t) {
            if ((int)$t['id'] === $id) { $deleted_trigger = $t; break; }
        }

        $triggers = array_values(array_filter($triggers, function($t) use ($id) { return (int)$t['id'] !== $id; }));
        $this->set_user_items($user_id, 'triggers', $triggers);

        // Sync removal to bot
        if ($deleted_trigger) {
            $this->sync_trigger_to_bot($user_id, $deleted_trigger, 'delete');
        }

        wp_send_json_success(['message' => 'تریگر حذف شد']);
    }

    public function handle_toggle_trigger() {
        $user_id = $this->ajax_require_auth();
        $triggers = $this->get_user_items($user_id, 'triggers');
        $id = isset($_POST['trigger_id']) ? intval($_POST['trigger_id']) : 0;
        if ($id <= 0) wp_send_json_error(['message' => 'تریگر نامعتبر']);
        foreach ($triggers as &$t) {
            if ((int)$t['id'] === $id) {
                $t['is_active'] = $t['is_active'] ? 0 : 1;
                // Sync toggle to bot
                $this->sync_trigger_to_bot($user_id, $t, 'toggle');
                break;
            }
        }
        unset($t);
        $this->set_user_items($user_id, 'triggers', $triggers);
        wp_send_json_success(['message' => 'تغییر وضعیت انجام شد']);
    }

    /**
     * Sync a trigger to a bot's auto_replies array.
     * This ensures triggers actually work via bot webhooks.
     */
    private function sync_trigger_to_bot($user_id, $trigger, $action) {
        $configs = $this->get_user_bot_configs($user_id);
        $platform = $trigger['platform'] ?? 'telegram';

        // Find existing bot for this platform, or create one
        $target_bot = null;
        foreach ($configs as &$cfg) {
            if (($cfg['platform'] ?? '') === $platform && !empty($cfg['is_active'])) {
                $target_bot = &$cfg;
                break;
            }
        }

        if (!$target_bot && !empty($configs)) {
            // Try any active bot
            foreach ($configs as &$cfg) {
                if (!empty($cfg['is_active'])) { $target_bot = &$cfg; break; }
            }
        }

        if (!$target_bot) {
            // Auto-create a minimal bot for this platform
            $bot_names = ['telegram' => 'بات تریگرها', 'bale' => 'بات تریگرها', 'rubika' => 'بات تریگرها'];
            $new_bot = [
                'id' => $this->next_id($configs),
                'name' => $bot_names[$platform] ?? 'بات تریگرها',
                'platform' => $platform,
                'token' => '',
                'is_active' => 0, // Inactive until token is set
                'welcome_message' => '',
                'webhook_url' => '',
                'commands' => [],
                'buttons' => [],
                'auto_replies' => [],
                'created_at' => current_time('mysql'),
            ];
            $configs[] = $new_bot;
            $target_bot = &$configs[count($configs) - 1];
        }

        if (empty($target_bot['auto_replies'])) $target_bot['auto_replies'] = [];

        $reply_data = [
            'id' => $trigger['id'],
            'trigger' => $trigger['keyword'],
            'response' => $trigger['response'],
            'match_type' => $trigger['match_type'] ?? 'exact',
            'is_active' => $trigger['is_active'] ? 1 : 0,
            'source' => 'trigger', // Mark as synced from triggers
        ];

        if ($action === 'add' || $action === 'toggle') {
            // Find and update or append
            $found = false;
            foreach ($target_bot['auto_replies'] as &$ar) {
                if ((int)($ar['id'] ?? 0) === (int)$trigger['id'] && ($ar['source'] ?? '') === 'trigger') {
                    $ar = $reply_data;
                    $found = true;
                    break;
                }
            }
            unset($ar);
            if (!$found) $target_bot['auto_replies'][] = $reply_data;
        } elseif ($action === 'delete') {
            $target_bot['auto_replies'] = array_values(array_filter($target_bot['auto_replies'], function($ar) use ($trigger) {
                return !((int)($ar['id'] ?? 0) === (int)$trigger['id'] && ($ar['source'] ?? '') === 'trigger');
            }));
        }

        // Save bot config
        $this->set_user_bot_configs($user_id, $configs);
    }

    public function handle_get_calendar() {
        $user_id = $this->ajax_require_auth();
        $month = intval($_GET['month'] ?? current_time('n'));
        $year = intval($_GET['year'] ?? current_time('Y'));
        $schedules = $this->get_user_items($user_id, 'schedules');
        $calendar_data = [];
        foreach ($schedules as $sch) {
            if (empty($sch['scheduled_at'])) continue;
            $date = date('Y-m-d', strtotime($sch['scheduled_at']));
            if (!isset($calendar_data[$date])) $calendar_data[$date] = [];
            $calendar_data[$date][] = ['id' => $sch['id'], 'title' => $sch['title'], 'time' => date('H:i', strtotime($sch['scheduled_at'])), 'status' => $sch['status']];
        }
        wp_send_json_success(['calendar' => $calendar_data, 'month' => $month, 'year' => $year]);
    }

    public function handle_update_schedule_time() {
        $user_id = $this->ajax_require_auth();
        $schedules = $this->get_user_items($user_id, 'schedules');
        $id = intval($_POST['schedule_id']);
        $new_time = sanitize_text_field($_POST['new_time'] ?? '');
        foreach ($schedules as &$sch) {
            if ((int)$sch['id'] === $id) { $sch['scheduled_at'] = $new_time; break; }
        }
        unset($sch);
        $this->set_user_items($user_id, 'schedules', $schedules);
        wp_send_json_success(['message' => 'زمان بروزرسانی شد']);
    }

    public function handle_schedule_batch() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'زمان‌بندی فقط در پلن Pro موجود است']);
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی برای زمان‌بندی وجود ندارد']);
        $start_datetime = sanitize_text_field($_POST['start_datetime'] ?? '');
        $interval = max(5, intval($_POST['interval'] ?? 60));
        $target = sanitize_text_field($_POST['target'] ?? 'queue');
        if (empty($start_datetime)) wp_send_json_error(['message' => 'تاریخ و ساعت شروع الزامی است']);
        $schedules = $this->get_user_items($user_id, 'schedules');
        $added = 0;
        $base_ts = strtotime($start_datetime);
        foreach ($items as $i => $item) {
            $sch_time = date('Y-m-d H:i:s', $base_ts + ($i * $interval * 60));
            $id = $this->next_id($schedules);
            $schedules[] = [
                'id' => $id,
                'title' => sanitize_text_field($item['title'] ?? ''),
                'message' => sanitize_textarea_field($item['message'] ?? ''),
                'hashtags' => sanitize_text_field($item['hashtags'] ?? ''),
                'scheduled_at' => $sch_time,
                'status' => 'pending',
                'recurring' => '',
                'target' => $target,
                'created_at' => current_time('mysql'),
            ];
            $added++;
        }
        $this->set_user_items($user_id, 'schedules', $schedules);
        wp_send_json_success(['message' => $added . ' محتوا زمان‌بندی شد (هر ' . $interval . ' دقیقه)']);
    }
}
