<?php
/**
 * Plugin Name: پورتال هوشمند اتوماسیون (نسخه ساده و کاربرپسند)
 * Description: پلتفرم SaaS با UI ساده، راهنمای گام به گام، و مدیریت چندین پلتفرم
 * Version: 15.0.0
 * Author: Your Name
 * Text Domain: smart-automation-pro
 */

if (!defined('ABSPATH')) exit;

// Early CORS handler — must load before any other plugin code
require_once __DIR__ . '/includes/ssp-cors.php';

// Constants
require_once __DIR__ . '/includes/constants.php';

// Traits (order matters: dependencies first)
require_once __DIR__ . '/includes/class-ssp-helpers.php';
require_once __DIR__ . '/includes/class-ssp-network.php';
require_once __DIR__ . '/includes/class-ssp-ai-api.php';
require_once __DIR__ . '/includes/class-ssp-messenger.php';
require_once __DIR__ . '/includes/class-ssp-queue.php';
require_once __DIR__ . '/includes/class-ssp-seo.php';
require_once __DIR__ . '/includes/class-ssp-link-shortener.php';
require_once __DIR__ . '/includes/class-ssp-bot-builder.php';
require_once __DIR__ . '/includes/class-ssp-wp-publish.php';
require_once __DIR__ . '/includes/class-ssp-email.php';
require_once __DIR__ . '/includes/class-ssp-cleanup.php';
require_once __DIR__ . '/includes/class-ssp-ajax-general.php';
require_once __DIR__ . '/includes/class-ssp-ajax-messengers.php';
require_once __DIR__ . '/includes/class-ssp-ajax-wp-sites.php';
require_once __DIR__ . '/includes/class-ssp-ajax-rss.php';
require_once __DIR__ . '/includes/class-ssp-ajax-drafts.php';
require_once __DIR__ . '/includes/class-ssp-ajax-templates.php';
require_once __DIR__ . '/includes/class-ssp-ajax-automation.php';
require_once __DIR__ . '/includes/class-ssp-ajax-admin.php';
require_once __DIR__ . '/includes/class-ssp-ajax-product.php';
require_once __DIR__ . '/includes/class-ssp-ai-browser.php';
require_once __DIR__ . '/includes/class-ssp-ajax-profiles.php';
require_once __DIR__ . '/includes/class-ssp-ajax-prompt-builder.php';
require_once __DIR__ . '/includes/class-ssp-content-distribution.php';

// Core class (uses all traits)
require_once __DIR__ . '/includes/class-ssp-core.php';

// Activation / Deactivation
register_activation_hook(__FILE__, ['SmartAutomationPro', 'activate']);
register_deactivation_hook(__FILE__, ['SmartAutomationPro', 'deactivate']);

// Boot
add_action('plugins_loaded', function() {
    SmartAutomationPro::init();
});
