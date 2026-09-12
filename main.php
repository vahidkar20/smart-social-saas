<?php
/**
 * Plugin Name: Smart Social SaaS (Refactored)
 * Plugin URI: https://smart-social.ir
 * Description: A complete SaaS for Social Media Automation, AI Content Generation, and Bot Building.
 * Version: 2.6.0
 * Author: Smart Social Team
 * Text Domain: smart-social-saas
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define Plugin Constants
define('SSP_VERSION', '2.6.0');
define('SSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSP_PLUGIN_FILE', __FILE__);

// Required Files (Order is important)
$ssp_includes = [
    'constants.php',
    'ssp-cors.php',
    'class-ssp-db.php',
    'class-ssp-helpers.php',
    'class-ssp-network.php',
    'class-ssp-ai-api.php',
    'class-ssp-ai-browser.php',
    'class-ssp-messenger.php',
    'class-ssp-queue.php',
    'class-ssp-seo.php',
    'class-ssp-link-shortener.php',
    'class-ssp-bot-builder.php',
    'class-ssp-wp-publish.php',
    'class-ssp-email.php',
    'class-ssp-cleanup.php',
    'class-ssp-content-distribution.php',
    'class-ssp-ajax-general.php',
    'class-ssp-ajax-messengers.php',
    'class-ssp-ajax-wp-sites.php',
    'class-ssp-ajax-rss.php',
    'class-ssp-ajax-drafts.php',
    'class-ssp-ajax-templates.php',
    'class-ssp-ajax-automation.php',
    'class-ssp-ajax-admin.php',
    'class-ssp-ajax-product.php',
    'class-ssp-ajax-profiles.php',
    'class-ssp-ajax-prompt-builder.php',
    'class-ssp-core.php'
];

foreach ($ssp_includes as $file) {
    $filepath = SSP_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    }
}

// Initialize Plugin
function run_smart_social_saas() {
    if (class_exists('SmartAutomationPro')) {
        SmartAutomationPro::init();
    } elseif (class_exists('SSP_Core')) {
        $plugin = new SSP_Core();
    }
}
add_action('plugins_loaded', 'run_smart_social_saas');

// Activation Hook
register_activation_hook(__FILE__, 'ssp_activate_plugin');
function ssp_activate_plugin() {
    if (class_exists('SSP_DB')) {
        SSP_DB::create_tables();
    }
}
