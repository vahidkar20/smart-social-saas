<?php
/**
 * SSP AI Browser Bridge Trait - Chatbot web interface integration
 *
 * Allows users to use chatbot web UIs (DeepSeek, ChatGPT) instead of API calls.
 * Works via a ScriptCat/Tampermonkey userscript that communicates with
 * the plugin through REST API endpoints.
 */
trait SSP_AiBrowserBridge {

    // ─── Token Management ───────────────────────────────────────────────

    private function get_or_create_bridge_token($user_id) {
        $token = get_user_meta($user_id, 'ssp_bridge_token', true);
        if (empty($token) || strlen($token) < 32) {
            $token = 'ssp_bridge_' . wp_generate_password(32, false);
            update_user_meta($user_id, 'ssp_bridge_token', $token);
        }
        return $token;
    }

    private function validate_bridge_token($token) {
        if (empty($token)) return 0;
        global $wpdb;
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ssp_bridge_token' AND meta_value = %s",
                $token
            )
        );
        return intval($user_id) ?: 0;
    }

    // ─── Task Lifecycle ─────────────────────────────────────────────────

    private function create_bridge_task($user_id, $prompt, $context = []) {
        $task_id = 'br_' . wp_generate_password(16, false);

        // Ensure prompt is properly formatted for browser delivery.
        // It should just be the raw text prompt, let the browser handle JSON encoding in the fetch request,
        // but we need to make sure the PHP output is a clean string.
        // If it's an array, encode it nicely. If it's a string, leave it.
        $clean_prompt = is_array($prompt) ? json_encode($prompt, JSON_UNESCAPED_UNICODE) : (string) $prompt;

        $task_data = [
            'task_id'    => $task_id,
            'user_id'    => $user_id,
            'prompt'     => $clean_prompt,
            'context'    => $context,
            'status'     => 'pending',
            'created_at' => current_time('mysql'),
        ];

        set_transient("ssp_bridge_task_{$task_id}", $task_data, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Maintain index of active tasks per user
        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        $active_ids[] = $task_id;
        update_user_meta($user_id, 'ssp_bridge_active_tasks', $active_ids);

        return $task_data;
    }

    private function cleanup_bridge_task($user_id, $task_id) {
        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        $active_ids = array_values(array_filter($active_ids, function($id) use ($task_id) {
            return $id !== $task_id;
        }));
        update_user_meta($user_id, 'ssp_bridge_active_tasks', $active_ids);
    }

    // ─── REST API Callbacks ─────────────────────────────────────────────

    public function bridge_get_pending_task($request) {
        $token = $request->get_header('X-SSP-Bridge-Token') ?: $request->get_header('x-ssp-bridge-token') ?: ($_SERVER['HTTP_X_SSP_BRIDGE_TOKEN'] ?? '') ?: $request->get_param('token');
        $user_id = $this->validate_bridge_token($token);
        if (!$user_id) {
            error_log('[SSP Bridge Pending] Invalid token: ' . $token);
            return new WP_REST_Response(['error' => 'Invalid token'], 401);
        }

        $active_ids = is_array($__tmp = get_user_meta($user_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        error_log('[SSP Bridge Pending] User: ' . $user_id . ' Active tasks count: ' . count($active_ids) . ' token: ' . substr($token, 0, 15) . '...');
        $found = null;

        foreach ($active_ids as $tid) {
            $task = get_transient("ssp_bridge_task_{$tid}");
            if ($task) {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' status: ' . $task['status']);
                // If it's pending OR sent, we return it. 
                // "sent" means we returned it before, but the client hasn't started processing it yet (which would change it to 'processing').
                // If the client fell back to fetch(), it might request it again.
                if ($task['status'] === 'pending' || $task['status'] === 'sent') {
                    $found = $task;
                    $task['status'] = 'sent';
                    set_transient("ssp_bridge_task_{$tid}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
                    break;
                }
            } else {
                error_log('[SSP Bridge Pending] Task ' . $tid . ' not found in transient');
            }
        }

        if (!$found) {
            return new WP_REST_Response(['status' => 'none'], 200);
        }

        return new WP_REST_Response([
            'task_id' => $found['task_id'],
            'prompt'  => $found['prompt'],
            'context' => $found['context'],
        ], 200);
    }

    public function bridge_receive_response($request) {
        $token = $request->get_header('X-SSP-Bridge-Token') ?: $request->get_header('x-ssp-bridge-token') ?: ($_SERVER['HTTP_X_SSP_BRIDGE_TOKEN'] ?? '');
        $raw_input = file_get_contents('php://input');
        $body = json_decode($raw_input, true);
        if (!$body) {
            $body = $request->get_json_params() ?: [];
        }
        if (empty($token)) {
            $token = $body['token'] ?? $request->get_param('token') ?? '';
        }

        $user_id = $this->validate_bridge_token($token);
        
        error_log('[SSP Bridge] Response received. Token: ' . substr($token, 0, 15) . '... User ID: ' . $user_id);
        error_log('[SSP Bridge] Raw input: ' . substr($raw_input, 0, 500));
        
        if (!$user_id) {
            error_log('[SSP Bridge] Invalid token!');
            return new WP_REST_Response(['error' => 'Invalid token'], 401);
        }
        
        error_log('[SSP Bridge] Body decoded: ' . json_encode($body));
        
        $task_id = sanitize_text_field($body['task_id'] ?? '');
        $response_text = wp_unslash($body['response_text'] ?? $body['response'] ?? $body['content'] ?? '');
        $status = sanitize_text_field($body['status'] ?? 'completed');

        error_log('[SSP Bridge] Task ID: ' . $task_id . ' Response length: ' . mb_strlen($response_text));

        if (empty($task_id) || (empty($response_text) && $status !== 'processing')) {
            error_log('[SSP Bridge] Missing task_id or response!');
            return new WP_REST_Response(['error' => 'Missing task_id or response'], 400);
        }

        $task = get_transient("ssp_bridge_task_{$task_id}");
        error_log('[SSP Bridge] Task found: ' . ($task ? 'yes' : 'no'));
        
        if (!$task || (int)$task['user_id'] !== (int)$user_id) {
            error_log('[SSP Bridge] Task not found or user mismatch!');
            return new WP_REST_Response(['error' => 'Task not found'], 404);
        }

        if ($status === 'processing') {
            $task['status'] = 'processing';
            set_transient("ssp_bridge_task_{$task_id}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);
            return new WP_REST_Response(['status' => 'ok'], 200);
        }

        // Store result
        $final_status = ($status === 'error') ? 'error' : 'completed';
        $result_data = [
            'task_id'       => $task_id,
            'response_text' => $response_text,
            'status'        => $final_status,
            'received_at'   => current_time('mysql'),
        ];
        set_transient("ssp_bridge_result_{$task_id}", $result_data, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Update task status
        $task['status'] = $final_status;
        set_transient("ssp_bridge_task_{$task_id}", $task, SSP_BROWSER_BRIDGE_TASK_TTL);

        // Cleanup active task index
        $this->cleanup_bridge_task($user_id, $task_id);

        error_log('[SSP Bridge] Response stored successfully with status: ' . $final_status);

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    public function bridge_get_task_status($request) {
        $token = $request->get_header('X-SSP-Bridge-Token');
        $user_id = $this->validate_bridge_token($token);
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Invalid token'], 401);
        }

        $task_id = sanitize_text_field($request->get_param('task_id') ?? '');
        if (empty($task_id)) {
            return new WP_REST_Response(['error' => 'Missing task_id'], 400);
        }

        $result = get_transient("ssp_bridge_result_{$task_id}");
        if ($result) {
            if ($result['status'] === 'error') {
                return new WP_REST_Response([
                    'status'  => 'error',
                    'message' => !empty($result['response_text']) ? $result['response_text'] : 'خطا در پردازش چت‌بات',
                ], 200);
            }
            $parsed = method_exists($this, 'parse_ai_json') ? $this->parse_ai_json($result['response_text']) : @json_decode($result['response_text'], true);
            // Normalize field aliases & restore newlines
            if ($parsed && is_array($parsed)) {
                if (empty($parsed['name']) && !empty($parsed['title'])) $parsed['name'] = $parsed['title'];
                if (empty($parsed['title']) && !empty($parsed['name'])) $parsed['title'] = $parsed['name'];
                if (empty($parsed['short_description']) && !empty($parsed['short_desc'])) $parsed['short_description'] = $parsed['short_desc'];
                if (empty($parsed['description']) && !empty($parsed['desc'])) $parsed['description'] = $parsed['desc'];
                if (empty($parsed['regular_price']) && !empty($parsed['price'])) $parsed['regular_price'] = $parsed['price'];
                if (empty($parsed['message']) && !empty($parsed['content'])) $parsed['message'] = $parsed['content'];
                if (empty($parsed['content']) && !empty($parsed['message'])) $parsed['content'] = $parsed['message'];

                foreach (['message', 'content', 'excerpt', 'short_description', 'description'] as $key) {
                    if (!empty($parsed[$key])) {
                        $parsed[$key] = str_replace('\\n', "\n", $parsed[$key]);
                        $parsed[$key] = str_replace('\\r', "\r", $parsed[$key]);
                        $parsed[$key] = str_replace('\\t', "\t", $parsed[$key]);
                    }
                }
            }
            return new WP_REST_Response([
                'status'      => 'completed',
                'raw'         => $result['response_text'],
                'parsed'      => $parsed,
                'received_at' => $result['received_at'],
            ], 200);
        }

        $task = get_transient("ssp_bridge_task_{$task_id}");
        if (!$task) {
            return new WP_REST_Response(['status' => 'expired'], 200);
        }

        return new WP_REST_Response(['status' => $task['status']], 200);
    }

    // ─── AJAX Handlers ──────────────────────────────────────────────────

    public function register_bridge_rewrite() {
        add_rewrite_rule('^ai-bridge\.user\.js$', 'index.php?ssp_ai_bridge=1', 'top');
    }

    public function add_bridge_query_var($vars) {
        $vars[] = 'ssp_ai_bridge';
        return $vars;
    }

    public function maybe_flush_bridge_rewrite() {
        $version = get_option('ssp_bridge_rewrite_version', '1.0');
        if (version_compare($version, '3.0', '<')) {
            $this->register_bridge_rewrite();
            flush_rewrite_rules();
            update_option('ssp_bridge_rewrite_version', '3.0');
        }
    }

    public function handle_bridge_userscript_rewrite() {
        if (!get_query_var('ssp_ai_bridge')) return;

        $token = sanitize_text_field($_GET['token'] ?? '');
        $site  = esc_url_raw($_GET['site'] ?? '');

        if (empty($token) || empty($site)) {
            status_header(400);
            echo 'Missing parameters';
            exit;
        }

        // Validate token belongs to a real user
        $user_id = $this->validate_bridge_token($token);
        if (!$user_id) {
            error_log('[SSP Bridge] Invalid token in .user.js request');
            status_header(403);
            echo 'Invalid token';
            exit;
        }

        // Validate site URL matches actual site
        $normalized_site = rtrim(strtolower($site), '/');
        $normalized_home = rtrim(strtolower(home_url()), '/');
        if (!empty($site) && $normalized_site !== $normalized_home) {
            $site_host = parse_url($site, PHP_URL_HOST);
            $home_host = parse_url(home_url(), PHP_URL_HOST);
            if ($site_host !== $home_host) {
                error_log('[SSP Bridge] Site URL mismatch in .user.js request: ' . $site . ' vs ' . home_url());
                status_header(403);
                echo 'Invalid site';
                exit;
            }
        }

        $script_path = plugin_dir_path(__FILE__) . '../assets/js/ai-bridge.user.js';
        $script = @file_get_contents($script_path);

        if ($script === false) {
            error_log('[SSP Bridge] Script file not found at: ' . $script_path);
            status_header(500);
            echo 'Script file not found';
            exit;
        }

        $injected = str_replace(
            ["'{{SSP_BRIDGE_TOKEN}}'", "'{{SSP_WP_SITE_URL}}'"],
            ["'" . addslashes($token) . "'", "'" . addslashes($site) . "'"],
            $script
        );

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header('Access-Control-Allow-Origin: ' . $origin);
        echo $injected;
        exit;
    }

    public function handle_bridge_setup() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $token = $this->get_or_create_bridge_token($user_id);
        $site_url = home_url();

        // URL for ScriptCat/Tampermonkey manual install via dashboard
        $script_url = home_url('/ai-bridge.user.js?token=' . urlencode($token) . '&site=' . urlencode($site_url));
        // Direct PHP fallback for copy-paste install
        $direct_url = plugin_dir_url(__DIR__) . 'ai-bridge-serve.php?token=' . urlencode($token) . '&site=' . urlencode($site_url);

        // Prepare full injected userscript code for 1-click copy into ScriptCat/Tampermonkey editor
        $script_path = plugin_dir_path(__FILE__) . '../assets/js/ai-bridge.user.js';
        $script_code = '';
        if (file_exists($script_path)) {
            $script_code = file_get_contents($script_path);
            $script_code = str_replace('{{SSP_BRIDGE_TOKEN}}', $token, $script_code);
            $script_code = str_replace('{{SSP_WP_SITE_URL}}', rtrim($site_url, '/'), $script_code);
        }

        wp_send_json_success([
            'token'         => $token,
            'site_url'      => $site_url,
            'script_url'    => $script_url,
            'direct_url'    => $direct_url,
            'script_code'   => $script_code,
            'poll_url'      => rest_url('ssp/v1/ai-bridge/pending'),
            'response_url'  => rest_url('ssp/v1/ai-bridge/response'),
            'status_url'    => rest_url('ssp/v1/ai-bridge/status'),
        ]);
    }

    public function handle_bridge_create_task() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $task_owner_id = $user_id;

        if ($this->get_user_plan($user_id) !== 'pro') {
            wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        }

        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $context_type = sanitize_text_field($_POST['context_type'] ?? 'general');

        if (empty($prompt)) {
            wp_send_json_error(['message' => 'پرامپت خالی است']);
        }

        // Enforce size limit
        if (mb_strlen($prompt) > 10000) {
            $prompt = mb_substr($prompt, 0, 10000);
        }

        // Check for existing pending task (use task_owner_id for impersonation support)
        $active_ids = is_array($__tmp = get_user_meta($task_owner_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
        $cleaned_ids = [];
        $has_pending = false;
        foreach ($active_ids as $tid) {
            $existing = get_transient("ssp_bridge_task_{$tid}");
            if (!$existing) {
                continue;
            }
            $cleaned_ids[] = $tid;
            if ($existing && in_array($existing['status'], ['pending', 'sent'])) {
                $has_pending = true;
            }
        }
        if (count($cleaned_ids) !== count($active_ids)) {
            update_user_meta($task_owner_id, 'ssp_bridge_active_tasks', $cleaned_ids);
        }

        // If there's a pending task, check if it's stale (older than 2 minutes)
        if ($has_pending) {
            $force = !empty($_POST['force']);
            if (!$force) {
                // Check if any pending task is stale
                $is_stale = false;
                foreach ($cleaned_ids as $tid) {
                    $existing = get_transient("ssp_bridge_task_{$tid}");
                    if ($existing && in_array($existing['status'], ['pending', 'sent'])) {
                        $created = strtotime($existing['created_at']);
                        if (time() - $created > 120) {
                            $is_stale = true;
                            // Auto-cancel stale task
                            delete_transient("ssp_bridge_task_{$tid}");
                            delete_transient("ssp_bridge_result_{$tid}");
                        }
                    }
                }
                if ($is_stale) {
                    // Refresh active IDs after cleanup
                    $active_ids = is_array($__tmp = get_user_meta($task_owner_id, 'ssp_bridge_active_tasks', true)) ? $__tmp : [];
                    $cleaned_ids = array_filter($active_ids, function($tid) {
                        return get_transient("ssp_bridge_task_{$tid}") !== false;
                    });
                    update_user_meta($task_owner_id, 'ssp_bridge_active_tasks', array_values($cleaned_ids));
                } else {
                    wp_send_json_error(['message' => 'یک تسک در حال انتظار وجود دارد. لطفاً صبر کنید یا تسک قبلی را لغو کنید.', 'can_force' => true]);
                }
            }
        }

        $task = $this->create_bridge_task($task_owner_id, $prompt, ['type' => $context_type]);

        wp_send_json_success([
            'task_id' => $task['task_id'],
            'chatbot' => get_user_meta($user_id, 'ssp_ai_chatbot', true) ?: 'deepseek',
        ]);
    }

    public function handle_bridge_poll_status() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        $task_id = sanitize_text_field($_POST['task_id'] ?? '');

        error_log('[SSP Bridge Poll] Polling for task: ' . $task_id . ' User: ' . $user_id);

        if (empty($task_id)) {
            wp_send_json_error(['message' => 'task_id الزامی است']);
        }

        $result = get_transient("ssp_bridge_result_{$task_id}");
        error_log('[SSP Bridge Poll] Result found: ' . ($result ? 'yes' : 'no'));
        
        if ($result) {
            if ($result['status'] === 'error') {
                wp_send_json_error([
                    'status'  => 'error',
                    'message' => !empty($result['response_text']) ? $result['response_text'] : 'خطا در پردازش چت‌بات',
                ]);
            }
            $parsed = method_exists($this, 'parse_ai_json') ? $this->parse_ai_json($result['response_text']) : @json_decode($result['response_text'], true);
            // Normalize field aliases & restore newlines
            if ($parsed && is_array($parsed)) {
                if (empty($parsed['name']) && !empty($parsed['title'])) $parsed['name'] = $parsed['title'];
                if (empty($parsed['title']) && !empty($parsed['name'])) $parsed['title'] = $parsed['name'];
                if (empty($parsed['short_description']) && !empty($parsed['short_desc'])) $parsed['short_description'] = $parsed['short_desc'];
                if (empty($parsed['description']) && !empty($parsed['desc'])) $parsed['description'] = $parsed['desc'];
                if (empty($parsed['regular_price']) && !empty($parsed['price'])) $parsed['regular_price'] = $parsed['price'];
                if (empty($parsed['message']) && !empty($parsed['content'])) $parsed['message'] = $parsed['content'];
                if (empty($parsed['content']) && !empty($parsed['message'])) $parsed['content'] = $parsed['message'];

                foreach (['message', 'content', 'excerpt', 'short_description', 'description'] as $key) {
                    if (!empty($parsed[$key])) {
                        $parsed[$key] = str_replace('\\n', "\n", $parsed[$key]);
                        $parsed[$key] = str_replace('\\r', "\r", $parsed[$key]);
                        $parsed[$key] = str_replace('\\t', "\t", $parsed[$key]);
                    }
                }
            }
            error_log('[SSP Bridge Poll] Parsed: ' . ($parsed ? 'yes' : 'no') . ' Raw length: ' . mb_strlen($result['response_text']));
            wp_send_json_success([
                'status' => 'completed',
                'raw'    => $result['response_text'],
                'parsed' => $parsed,
            ]);
        } else {
            $task = get_transient("ssp_bridge_task_{$task_id}");
            error_log('[SSP Bridge Poll] Task found: ' . ($task ? 'yes' : 'no'));
            if (!$task) {
                wp_send_json_success(['status' => 'expired']);
            } else {
                wp_send_json_success(['status' => $task['status']]);
            }
        }
    }

    public function handle_bridge_cancel_task() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        $task_id = sanitize_text_field($_POST['task_id'] ?? '');

        if (!empty($task_id)) {
            delete_transient("ssp_bridge_task_{$task_id}");
            delete_transient("ssp_bridge_result_{$task_id}");
            $this->cleanup_bridge_task($user_id, $task_id);
        }

        wp_send_json_success(['message' => 'تسک لغو شد']);
    }
}
