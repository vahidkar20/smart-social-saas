<?php
/**
 * SSP Constants - Plugin configuration values
 */
if (!defined('ABSPATH')) exit;

define('SSP_VERSION', '15.0.0');
define('SSP_CRON_INTERVAL', 5 * MINUTE_IN_SECONDS);
define('SSP_MSG_DELAY', 1.5);
define('SSP_WP_POST_DELAY', 3);
define('SSP_AI_RETRY_DELAY', 5);
define('SSP_MAX_DRAFTS', 50);
define('SSP_MAX_TEMPLATES', 20);
define('SSP_META_DESC_LENGTH', 155);
define('SSP_MAX_HASHTAGS', 10);
define('SSP_WHATSAPP_API_VERSION', 'v18.0');
define('SSP_BOT_BUILDER_MAX_COMMANDS', 20);
define('SSP_BOT_BUILDER_MAX_BUTTONS', 10);
define('SSP_BOT_BUILDER_MAX_CHILD_BUTTONS', 8);
define('SSP_BOT_BUILDER_MAX_AUTO_REPLIES', 50);
define('SSP_BOT_BUILDER_MAX_SCENARIO_STEPS', 10);
define('SSP_BOT_BUILDER_MAX_SCENARIO_BUTTONS', 4);
define('SSP_BOT_HEALTH_CHECK_INTERVAL', 3600);
define('SSP_BOT_RETRY_MAX_ATTEMPTS', 3);
define('SSP_BOT_RETRY_BASE_DELAY', 2);

// Performance & Safety Constants
define('SSP_MAX_LOGS', 200);
define('SSP_LOG_CLEANUP_INTERVAL', 86400);
define('SSP_WEBHOOK_RATE_LIMIT', 30);
define('SSP_WEBHOOK_TIMEOUT', 5);
define('SSP_AI_CACHE_TTL', HOUR_IN_SECONDS);
define('SSP_BOT_CONFIG_CACHE_TTL', 300);
define('SSP_MAX_BOTS_PER_USER', 5);
define('SSP_TOKEN_ENCRYPTION_KEY', 'ssp_bot_');

// Browser Bridge (SnapMonkey userscript)
define('SSP_BROWSER_BRIDGE_TASK_TTL', 5 * MINUTE_IN_SECONDS);
define('SSP_BROWSER_BRIDGE_POLL_INTERVAL', 3);
define('SSP_BROWSER_BRIDGE_MAX_WAIT', 5 * MINUTE_IN_SECONDS);

// Telegram Relay (Self-hosted PHP)
define('SSP_RELAY_DEFAULT_TIMEOUT', 30);

// DNS over HTTPS (DoH) Servers
define('SSP_DOH_SERVERS', [
    'cloudflare' => 'https://1.1.1.1/dns-query',
    'cloudflare_1001' => 'https://1.0.0.1/dns-query',
    'cloudflare_family' => 'https://family.cloudflare-dns.com/dns-query',
    'cloudflare_security' => 'https://security.cloudflare-dns.com/dns-query',
    'google' => 'https://dns.google/dns-query',
    'google_8844' => 'https://dns.google:8443/dns-query',
    'quad9' => 'https://dns.quad9.net/dns-query',
    'adguard' => 'https://dns.adguard.com/dns-query',
    'mullvad' => 'https://dns.mullvad.net/dns-query',
    'nextdns' => 'https://firefox.dns.nextdns.io/dns-query',
    'opendns' => 'https://doh.opendns.com/dns-query',
    'cleanbrowsing' => 'https://doh.cleanbrowsing.org/dns-query',
]);
