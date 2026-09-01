<?php
/**
 * SSP Core - Main class shell, init, hook registration
 *
 * This class uses ALL traits to compose the full plugin functionality.
 */
final class SmartAutomationPro {

    use SSP_Helpers, SSP_Network, SSP_AiApi, SSP_AiBrowserBridge, SSP_Messenger, SSP_Queue,
        SSP_Seo, SSP_LinkShortener, SSP_BotBuilder, SSP_WpPublish,
        SSP_Email, SSP_Cleanup, SSP_ContentDistribution,
        SSP_AjaxGeneral, SSP_AjaxMessengers, SSP_AjaxWpSites,
        SSP_AjaxRss, SSP_AjaxDrafts, SSP_AjaxTemplates,
        SSP_AjaxAutomation, SSP_AjaxAdmin, SSP_AjaxProduct,
        SSP_AjaxProfiles, SSP_AjaxPromptBuilder;

    private static $instance;

    public static function init() {
        if (!self::$instance) self::$instance = new self();

        // Run style cleanup if pending
        if (get_option('ssp_style_cleanup_pending', '0') === '1') {
            self::$instance->cleanup_all_bot_buttons_style();
            delete_option('ssp_style_cleanup_pending');
        }

        return self::$instance;
    }

    private function __construct() {
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
        add_action('ssp_process_queue_hook', [$this, 'process_queue']);
        add_action('ssp_process_distributions_hook', [$this, 'process_distributions']);
        add_action('ssp_ai_generation_hook', [$this, 'auto_generate_content']);
        add_action('ssp_rss_fetch_hook', [$this, 'process_rss_feeds']);
        add_action('ssp_daily_summary_hook', [$this, 'send_daily_summary']);
        add_action('ssp_cleanup_hook', [$this, 'run_cleanup']);
        add_action('transition_post_status', [$this, 'on_post_publish'], 10, 3);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_shortcode('ssp_portal', [$this, 'render_shortcode']);
        add_action('template_redirect', [$this, 'handle_short_link_redirect']);
        add_action('init', [$this, 'disable_emoji'], 1);
        add_action('wp_enqueue_scripts', [$this, 'cleanup_frontend_assets'], 999);
        add_filter('show_admin_bar', function($show) {
            if (!is_admin()) return false;
            return $show;
        });

        // AJAX Handlers - General
        add_action('wp_ajax_ssp_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_ssp_test_connection', [$this, 'handle_test_connection']);
        add_action('wp_ajax_ssp_test_ai', [$this, 'handle_test_ai']);
        add_action('wp_ajax_ssp_test_ai_live', [$this, 'handle_test_ai_live']);
        add_action('wp_ajax_ssp_activate_license', [$this, 'handle_activate_license']);
        add_action('wp_ajax_ssp_clear_logs', [$this, 'handle_clear_logs']);
        add_action('wp_ajax_ssp_generate_now', [$this, 'handle_generate_now']);
        add_action('wp_ajax_ssp_generate_ai_content', [$this, 'handle_generate_ai_content']);
        add_action('wp_ajax_ssp_manual_send', [$this, 'handle_manual_send']);
        add_action('wp_ajax_ssp_upload_media', [$this, 'handle_upload_media']);

        // AJAX Handlers - Messengers
        add_action('wp_ajax_ssp_add_messenger', [$this, 'handle_add_messenger']);
        add_action('wp_ajax_ssp_delete_messenger', [$this, 'handle_delete_messenger']);
        add_action('wp_ajax_ssp_get_messengers', [$this, 'handle_get_messengers']);
        add_action('wp_ajax_ssp_update_messenger', [$this, 'handle_update_messenger']);

        // AJAX Handlers - WP Sites
        add_action('wp_ajax_ssp_add_wp_site', [$this, 'handle_add_wp_site']);
        add_action('wp_ajax_ssp_delete_wp_site', [$this, 'handle_delete_wp_site']);
        add_action('wp_ajax_ssp_update_wp_site', [$this, 'handle_update_wp_site']);
        add_action('wp_ajax_ssp_test_wp_site', [$this, 'handle_test_wp_site']);
        add_action('wp_ajax_ssp_fetch_wp_categories', [$this, 'handle_fetch_wp_categories']);

        // AJAX Handlers - RSS
        add_action('wp_ajax_ssp_add_rss_feed', [$this, 'handle_add_rss_feed']);
        add_action('wp_ajax_ssp_delete_rss_feed', [$this, 'handle_delete_rss_feed']);
        add_action('wp_ajax_ssp_update_rss_feed', [$this, 'handle_update_rss_feed']);
        add_action('wp_ajax_ssp_fetch_rss_now', [$this, 'handle_fetch_rss_now']);
        add_action('wp_ajax_ssp_rss_send_selected', [$this, 'handle_rss_send_selected']);
        add_action('wp_ajax_ssp_rss_save_drafts', [$this, 'handle_rss_save_drafts']);
        add_action('wp_ajax_ssp_rss_schedule', [$this, 'handle_rss_schedule']);
        add_action('wp_ajax_ssp_rss_ai_process', [$this, 'handle_rss_ai_process']);
        add_action('wp_ajax_ssp_rss_content_batch', [$this, 'handle_rss_content_batch']);

        // AJAX Handlers - Admin
        add_action('wp_ajax_ssp_manual_process', [$this, 'handle_manual_process']);
        add_action('wp_ajax_ssp_generate_license', [$this, 'handle_generate_license']);
        add_action('wp_ajax_ssp_revoke_license', [$this, 'handle_revoke_license']);
        add_action('wp_ajax_ssp_update_license_expiry', [$this, 'handle_update_license_expiry']);
        add_action('wp_ajax_ssp_activate_cron', [$this, 'handle_activate_cron']);
        add_action('wp_ajax_ssp_get_user_list', [$this, 'handle_get_user_list']);
        add_action('wp_ajax_ssp_impersonate_user', [$this, 'handle_impersonate_user']);
        add_action('wp_ajax_ssp_stop_impersonation', [$this, 'handle_stop_impersonation']);
        add_action('wp_ajax_ssp_retry_message', [$this, 'handle_retry_message']);
        add_action('wp_ajax_ssp_cancel_queue_item', [$this, 'handle_cancel_queue_item']);
        add_action('wp_ajax_ssp_cancel_all_queue', [$this, 'handle_cancel_all_queue']);
        add_action('wp_ajax_ssp_cancel_schedule', [$this, 'handle_cancel_schedule']);

        // AJAX Handlers - Automation
        add_action('wp_ajax_ssp_add_schedule', [$this, 'handle_add_schedule']);
        add_action('wp_ajax_ssp_delete_schedule', [$this, 'handle_delete_schedule']);
        add_action('wp_ajax_ssp_schedule_batch', [$this, 'handle_schedule_batch']);
        add_action('wp_ajax_ssp_add_trigger', [$this, 'handle_add_trigger']);
        add_action('wp_ajax_ssp_delete_trigger', [$this, 'handle_delete_trigger']);
        add_action('wp_ajax_ssp_toggle_trigger', [$this, 'handle_toggle_trigger']);
        add_action('ssp_process_schedules_hook', [$this, 'process_schedules']);

        // AJAX Handlers - Drafts
        add_action('wp_ajax_ssp_save_draft', [$this, 'handle_save_draft']);
        add_action('wp_ajax_ssp_get_drafts', [$this, 'handle_get_drafts']);
        add_action('wp_ajax_ssp_delete_draft', [$this, 'handle_delete_draft']);
        add_action('wp_ajax_ssp_update_draft', [$this, 'handle_update_draft']);

        // AJAX Handlers - Templates
        add_action('wp_ajax_ssp_save_template_item', [$this, 'handle_save_template_item']);
        add_action('wp_ajax_ssp_get_templates', [$this, 'handle_get_templates']);
        add_action('wp_ajax_ssp_delete_template_item', [$this, 'handle_delete_template_item']);
        add_action('wp_ajax_ssp_set_template_default', [$this, 'handle_set_template_default']);
        add_action('wp_ajax_ssp_increment_template_usage', [$this, 'handle_increment_template_usage']);

        // AJAX Handlers - Calendar
        add_action('wp_ajax_ssp_get_calendar', [$this, 'handle_get_calendar']);
        add_action('wp_ajax_ssp_update_schedule_time', [$this, 'handle_update_schedule_time']);

        // AJAX Handlers - SEO
        add_action('wp_ajax_ssp_analyze_seo', [$this, 'handle_analyze_seo']);
        add_action('wp_ajax_ssp_generate_meta_desc', [$this, 'handle_generate_meta_desc']);
        add_action('wp_ajax_ssp_seo_suggest_title', [$this, 'handle_seo_suggest_title']);
        add_action('wp_ajax_ssp_keyword_analyzer', [$this, 'handle_keyword_analyzer']);
        add_action('wp_ajax_ssp_serp_preview', [$this, 'handle_serp_preview']);
        add_action('wp_ajax_ssp_url_auditor', [$this, 'handle_url_auditor']);
        add_action('wp_ajax_ssp_geo_analyze', [$this, 'handle_geo_analyze']);
        add_action('wp_ajax_ssp_schema_generator', [$this, 'handle_schema_generator']);
        add_action('wp_ajax_ssp_eeat_analyze', [$this, 'handle_eeat_analyze']);
        add_action('wp_ajax_ssp_content_gap', [$this, 'handle_content_gap']);
        add_action('wp_ajax_ssp_topic_cluster', [$this, 'handle_topic_cluster']);
        add_action('wp_ajax_ssp_seo_checklist', [$this, 'handle_seo_checklist']);
        add_action('wp_ajax_ssp_featured_snippet', [$this, 'handle_featured_snippet']);
        add_action('wp_ajax_ssp_voice_search', [$this, 'handle_voice_search']);
        add_action('wp_ajax_ssp_site_audit', [$this, 'handle_site_audit']);

        // AJAX Handlers - Link Shortener
        add_action('wp_ajax_ssp_shorten_url', [$this, 'handle_shorten_url']);
        add_action('wp_ajax_ssp_save_link_settings', [$this, 'handle_save_link_settings']);
        add_action('wp_ajax_ssp_get_short_links', [$this, 'handle_get_short_links']);
        add_action('wp_ajax_ssp_delete_short_link', [$this, 'handle_delete_short_link']);
        add_action('wp_ajax_ssp_save_short_url_format', [$this, 'handle_save_short_url_format']);
        add_action('wp_ajax_ssp_save_custom_domain', [$this, 'handle_save_custom_domain']);

        // AJAX Handlers - Proxy/Network
        add_action('wp_ajax_ssp_save_proxy_settings', [$this, 'handle_save_proxy_settings']);
        add_action('wp_ajax_ssp_test_proxy', [$this, 'handle_test_proxy']);
        add_action('wp_ajax_ssp_network_diagnostics', [$this, 'handle_network_diagnostics']);
        add_action('wp_ajax_ssp_save_doh_settings', [$this, 'handle_save_doh_settings']);
        add_action('wp_ajax_ssp_test_doh', [$this, 'handle_test_doh']);
        add_action('wp_ajax_ssp_test_telegram_dns', [$this, 'handle_test_telegram_dns']);
        add_action('wp_ajax_ssp_save_telegram_relay', [$this, 'handle_save_telegram_relay']);
        add_action('wp_ajax_ssp_test_telegram_relay', [$this, 'handle_test_telegram_relay']);

        // AJAX Handlers - RSS Auto-Fetch
        add_action('wp_ajax_ssp_fetch_rss_now', [$this, 'handle_fetch_rss_now']);

        // AJAX Handlers - UTM
        add_action('wp_ajax_ssp_save_utm_settings', [$this, 'handle_save_utm_settings']);

        // AJAX Handlers - Email
        add_action('wp_ajax_ssp_save_email_settings', [$this, 'handle_save_email_settings']);
        add_action('wp_ajax_ssp_test_email', [$this, 'handle_test_email']);

        // AJAX Handlers - AI Image
        add_action('wp_ajax_ssp_generate_image', [$this, 'handle_generate_image']);
        add_action('wp_ajax_ssp_save_image_settings', [$this, 'handle_save_image_settings']);

        // AJAX Handlers - AI SEO
        add_action('wp_ajax_ssp_analyze_seo_ai', [$this, 'handle_analyze_seo_ai']);

        // AJAX Handlers - Batch
        add_action('wp_ajax_ssp_batch_generate', [$this, 'handle_batch_generate']);
        add_action('wp_ajax_ssp_send_batch', [$this, 'handle_send_batch']);

        // AJAX Handlers - Browser Bridge
        add_action('wp_ajax_ssp_bridge_setup', [$this, 'handle_bridge_setup']);
        add_action('wp_ajax_ssp_bridge_create_task', [$this, 'handle_bridge_create_task']);
        add_action('wp_ajax_ssp_bridge_poll_status', [$this, 'handle_bridge_poll_status']);
        add_action('wp_ajax_ssp_bridge_cancel_task', [$this, 'handle_bridge_cancel_task']);

        // Browser Bridge - Rewrite rule for .user.js URL
        add_action('init', [$this, 'register_bridge_rewrite']);
        add_filter('query_vars', [$this, 'add_bridge_query_var']);
        add_action('template_redirect', [$this, 'handle_bridge_userscript_rewrite'], 1);
        add_action('init', [$this, 'maybe_flush_bridge_rewrite']);

        // CORS for Browser Bridge REST API
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-SSP-Bridge-Token');
                header('Access-Control-Allow-Credentials: true');
                if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    status_header(200);
                    exit;
                }
                return $value;
            });
        }, 15);

        // AJAX Handlers - Product Generator
        add_action('wp_ajax_ssp_generate_product', [$this, 'handle_generate_product']);
        add_action('wp_ajax_ssp_generate_product_ai', [$this, 'handle_generate_product_ai']);
        add_action('wp_ajax_ssp_upload_product_image', [$this, 'handle_upload_product_image']);
        add_action('wp_ajax_ssp_upload_post_image', [$this, 'handle_upload_post_image']);
        add_action('wp_ajax_ssp_brainstorm_ideas', [$this, 'handle_brainstorm_ideas']);
        add_action('wp_ajax_ssp_fetch_woo_categories', [$this, 'handle_fetch_woo_categories']);
        add_action('wp_ajax_ssp_save_product_prompt', [$this, 'handle_save_product_prompt']);
        add_action('wp_ajax_ssp_save_product_template', [$this, 'handle_save_product_template']);
        add_action('wp_ajax_ssp_get_product_templates', [$this, 'handle_get_product_templates']);
        add_action('wp_ajax_ssp_delete_product_template', [$this, 'handle_delete_product_template']);
        add_action('wp_ajax_ssp_bulk_generate_products', [$this, 'handle_bulk_generate_products']);
        add_action('wp_ajax_ssp_get_publish_history', [$this, 'handle_get_publish_history']);
        add_action('wp_ajax_ssp_clear_publish_history', [$this, 'handle_clear_publish_history']);
        add_action('wp_ajax_ssp_fetch_remote_product', [$this, 'handle_fetch_remote_product']);
        add_action('wp_ajax_ssp_fetch_woo_brands', [$this, 'handle_fetch_woo_brands']);
        add_action('wp_ajax_ssp_fetch_woo_shipping', [$this, 'handle_fetch_woo_shipping']);

        // AJAX Handlers - Bot Builder
        add_action('wp_ajax_ssp_save_bot_config', [$this, 'handle_save_bot_config']);
        add_action('wp_ajax_ssp_delete_bot', [$this, 'handle_delete_bot']);
        add_action('wp_ajax_ssp_get_bot_config', [$this, 'handle_get_bot_config']);
        add_action('wp_ajax_ssp_add_bot_command', [$this, 'handle_add_bot_command']);
        add_action('wp_ajax_ssp_delete_bot_command', [$this, 'handle_delete_bot_command']);
        add_action('wp_ajax_ssp_update_bot_command', [$this, 'handle_update_bot_command']);
        add_action('wp_ajax_ssp_add_bot_button', [$this, 'handle_add_bot_button']);
        add_action('wp_ajax_ssp_delete_bot_button', [$this, 'handle_delete_bot_button']);
        add_action('wp_ajax_ssp_update_bot_button', [$this, 'handle_update_bot_button']);
        add_action('wp_ajax_ssp_add_bot_auto_reply', [$this, 'handle_add_bot_auto_reply']);
        add_action('wp_ajax_ssp_delete_bot_auto_reply', [$this, 'handle_delete_bot_auto_reply']);
        add_action('wp_ajax_ssp_update_bot_auto_reply', [$this, 'handle_update_bot_auto_reply']);
        add_action('wp_ajax_ssp_set_bot_welcome', [$this, 'handle_set_bot_welcome']);
        add_action('wp_ajax_ssp_set_bot_menu', [$this, 'handle_set_bot_menu']);
        add_action('wp_ajax_ssp_test_bot_webhook', [$this, 'handle_test_bot_webhook']);
        add_action('wp_ajax_ssp_set_bot_webhook', [$this, 'handle_set_bot_webhook']);
        add_action('wp_ajax_ssp_get_bot_stats', [$this, 'handle_get_bot_stats']);
        add_action('wp_ajax_ssp_generate_bot_code', [$this, 'handle_generate_bot_code']);
        add_action('wp_ajax_ssp_debug_bot_list', [$this, 'handle_debug_bot_list']);
        add_action('wp_ajax_ssp_debug_bot_buttons', [$this, 'handle_debug_bot_buttons']);

        // AJAX Handlers - Bot Templates
        add_action('wp_ajax_ssp_load_bot_template', [$this, 'handle_load_bot_template']);
        add_action('wp_ajax_ssp_apply_bot_template', [$this, 'handle_apply_bot_template']);

        // AJAX Handlers - Bot Scenarios
        add_action('wp_ajax_ssp_save_bot_scenario', [$this, 'handle_save_bot_scenario']);
        add_action('wp_ajax_ssp_delete_bot_scenario', [$this, 'handle_delete_bot_scenario']);

        // AJAX Handlers - Messenger Health
        add_action('wp_ajax_ssp_check_messenger_health', [$this, 'handle_check_messenger_health']);
        add_action('wp_ajax_ssp_validate_messenger_token', [$this, 'handle_validate_messenger_token']);

        // AJAX Handlers - Profiles
        add_action('wp_ajax_ssp_get_profiles', [$this, 'handle_get_profiles']);
        add_action('wp_ajax_ssp_add_profile', [$this, 'handle_add_profile']);
        add_action('wp_ajax_ssp_update_profile', [$this, 'handle_update_profile']);
        add_action('wp_ajax_ssp_switch_profile', [$this, 'handle_switch_profile']);
        add_action('wp_ajax_ssp_delete_profile', [$this, 'handle_delete_profile']);

        // AJAX Handlers - Prompt Builder
        add_action('wp_ajax_ssp_save_prompt_template', [$this, 'handle_save_prompt_template']);
        add_action('wp_ajax_ssp_get_prompt_templates', [$this, 'handle_get_prompt_templates']);
        add_action('wp_ajax_ssp_delete_prompt_template', [$this, 'handle_delete_prompt_template']);
        add_action('wp_ajax_ssp_preview_prompt', [$this, 'handle_preview_prompt']);

        // AJAX Handlers - Content Distribution
        add_action('wp_ajax_ssp_get_distributions', [$this, 'handle_get_distributions']);
        add_action('wp_ajax_ssp_save_distribution', [$this, 'handle_save_distribution']);
        add_action('wp_ajax_ssp_delete_distribution', [$this, 'handle_delete_distribution']);
        add_action('wp_ajax_ssp_toggle_distribution', [$this, 'handle_toggle_distribution']);
        add_action('wp_ajax_ssp_preview_distribution', [$this, 'handle_preview_distribution']);
        add_action('wp_ajax_ssp_run_distribution_now', [$this, 'handle_run_distribution_now']);

        // REST API - Bot Webhook
        add_action('rest_api_init', function() {
            register_rest_route('ssp/v1', '/bot-webhook/(?P<bot_id>\d+)', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_bot_webhook'],
                'permission_callback' => '__return_true',
            ]);

            // Browser Bridge REST routes
            register_rest_route('ssp/v1', '/ai-bridge/pending', [
                'methods' => 'GET',
                'callback' => [$this, 'bridge_get_pending_task'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('ssp/v1', '/ai-bridge/response', [
                'methods' => 'POST',
                'callback' => [$this, 'bridge_receive_response'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('ssp/v1', '/ai-bridge/status', [
                'methods' => 'GET',
                'callback' => [$this, 'bridge_get_task_status'],
                'permission_callback' => '__return_true',
            ]);
        });

        add_action('admin_notices', [$this, 'show_cron_notice']);
    }

    public static function activate() {
        if (!wp_next_scheduled('ssp_process_queue_hook')) {
            wp_schedule_event(time() + 60, 'ssp_two_minutes', 'ssp_process_queue_hook');
        }
        if (!wp_next_scheduled('ssp_process_distributions_hook')) {
            wp_schedule_event(time() + 60, 'ssp_two_minutes', 'ssp_process_distributions_hook');
        }
        if (!wp_next_scheduled('ssp_ai_generation_hook')) {
            wp_schedule_event(time() + 120, 'hourly', 'ssp_ai_generation_hook');
        }
        if (!wp_next_scheduled('ssp_process_schedules_hook')) {
            wp_schedule_event(time() + 60, 'ssp_two_minutes', 'ssp_process_schedules_hook');
        }
        if (!wp_next_scheduled('ssp_rss_fetch_hook')) {
            wp_schedule_event(time() + 90, 'ssp_two_minutes', 'ssp_rss_fetch_hook');
        }
        if (!wp_next_scheduled('ssp_daily_summary_hook')) {
            wp_schedule_event(time() + 3600, 'daily', 'ssp_daily_summary_hook');
        }
        if (!wp_next_scheduled('ssp_cleanup_hook')) {
            wp_schedule_event(time() + 1800, 'daily', 'ssp_cleanup_hook');
        }
        add_rewrite_rule('^ai-bridge\.user\.js$', 'index.php?ssp_ai_bridge=1', 'top');
        flush_rewrite_rules();
        update_option('ssp_bridge_rewrite_version', '3.0');

        // Run style cleanup once (version check)
        $style_cleanup_version = get_option('ssp_style_cleanup_version', '0');
        if (version_compare($style_cleanup_version, '15.1.0', '<')) {
            // Schedule cleanup to run on next page load
            update_option('ssp_style_cleanup_pending', '1');
            update_option('ssp_style_cleanup_version', '15.1.0');
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('ssp_process_queue_hook');
        wp_clear_scheduled_hook('ssp_process_distributions_hook');
        wp_clear_scheduled_hook('ssp_ai_generation_hook');
        wp_clear_scheduled_hook('ssp_process_schedules_hook');
        wp_clear_scheduled_hook('ssp_rss_fetch_hook');
        wp_clear_scheduled_hook('ssp_daily_summary_hook');
        wp_clear_scheduled_hook('ssp_cleanup_hook');
    }

    public function add_cron_interval($schedules) {
        $schedules['ssp_two_minutes'] = ['interval' => SSP_CRON_INTERVAL, 'display' => 'هر ۵ دقیقه'];
        return $schedules;
    }

    public function add_admin_menu() {
        add_menu_page('پورتال هوشمند', 'اتوماسیون من', 'read', 'smart-automation',
                     [$this, 'render_admin_page'], 'dashicons-superhero-alt', 30);
        add_submenu_page('smart-automation', 'پنل مدیریت', 'پنل مدیریت', 'manage_options',
                        'ssp-admin-panel', [$this, 'render_admin_panel']);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'smart-automation') === false && strpos($hook, 'ssp-admin-panel') === false) return;
        wp_enqueue_style('ssp-admin-css', plugin_dir_url(__FILE__) . '../assets/css/admin.css', [], SSP_VERSION);
    }

    public function render_admin_page() { echo $this->render_portal(false); }

    public function render_shortcode() {
        wp_enqueue_script('jquery');
        add_filter('body_class', function($classes) {
            $classes[] = 'ssp-shortcode-page';
            return $classes;
        });
        return $this->render_portal(true);
    }

    public function disable_emoji() {
        remove_action('wp_head', 'wp_enqueue_emojis', 1);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        wp_deregister_style('wp-emoji-styles');
        wp_deregister_script('wp-emoji');
    }

    public function cleanup_frontend_assets() {
        if (is_admin()) return;
        wp_deregister_style('dashicons');
        wp_deregister_script('hoverintent-js');
        wp_deregister_script('admin-bar');
        wp_deregister_style('admin-bar');
        wp_deregister_script('wp-util');
        wp_deregister_script('wp-a11y');
        wp_deregister_script('wp-api-fetch');
        wp_deregister_script('wp-url');
    }

    public function show_cron_notice() {
        if (!current_user_can('manage_options')) return;
        if (!wp_next_scheduled('ssp_process_queue_hook')) {
            $activate_url = wp_nonce_url(admin_url('admin.php?page=ssp-admin-panel&activate_cron=1'), 'ssp_activate_cron');
            echo '<div class="notice notice-warning"><p><strong>SSP:</strong> سیستم خودکار غیرفعال است. <a href="' . $activate_url . '">فعال‌سازی Cron Jobs</a></p></div>';
        }
    }

    public function render_admin_panel() {
        require __DIR__ . '/views/admin-panel.php';
    }

    public function render_portal($is_frontend) {
        ob_start();
        require __DIR__ . '/views/portal.php';
        return ob_get_clean();
    }
}
