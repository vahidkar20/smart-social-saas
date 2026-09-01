# Smart Automation Pro — Project Reference

> **TRUST RULE**: This file is a MAP, not the TERRITORY. Before making any change:
> 1. ALWAYS read the actual source file you're about to edit
> 2. NEVER trust line numbers — they WILL be stale after edits
> 3. NEVER trust function signatures blindly — verify in the source
> 4. If this file contradicts actual code, the actual code ALWAYS wins
> 5. Use this file to orient yourself, then VERIFY before editing

> **DISCLAIMER**: This file may contain INCOMPLETE, OUTDATED, or INCORRECT information.
> ALWAYS verify by scanning the actual source code before making changes.

---

## 1. OVERVIEW

WordPress SaaS plugin for content automation. Persian/Farsi RTL UI with IRANSansX font.
- **Version**: 15.0.0 | **TEXT_DOMAIN**: smart-automation-pro
- **Primary file**: `main.php` (54 lines)
- **Architecture**: Singleton `SmartAutomationPro` (class-ssp-core.php) + 24 PHP traits
- **Storage**: wp_options (global) + wp_usermeta (per-user) — NO custom DB tables
- **Languages**: PHP 7.4+, JavaScript (vanilla), CSS (custom properties, light/dark)
- **Framework**: WordPress Plugin (no composer, no npm, no build tools)

### Quick Stats
- 31 PHP files, 1 JS userscript (424 lines), 2 CSS files, 2 view files
- Total: ~29,129 lines of code
- 24 traits composed into 1 final class
- 156 AJAX handlers (all wp_ajax_, 0 wp_ajax_nopriv) | 4 REST routes | 6 WP-Cron events
- 177 total hooks registered in class-ssp-core.php (actions + filters)
- 6 messenger platforms | 6 AI providers | 3 bot platforms
- 23 UI tabs (8 sidebar sections) | 7 SEO sub-tabs | 16 SEO tools
- Bot builder: 12 button types, templates, scenarios (61 functions)
- Browser bridge: DeepSeek/ChatGPT web UI automation (14 functions in userscript)
- SEO module: 29 functions for 16 SEO tools
- Helpers trait: 44 methods including auth, storage, utilities
- Frontend JS: Data stored in JS variables (messengerData, wpSiteData, rssFeedData, profileData), NOT HTML attributes

---

## 2. FILE MAP

### Bootstrap & Config
| File | Lines | Purpose |
|------|-------|---------|
| `main.php` | 54 | Entry point: requires ssp-cors.php (early CORS), constants, 24 traits, core. Registers activation/deactivation hooks. |
| `includes/constants.php` | 59 | All SSP_* constants (see §7) |
| `includes/class-ssp-core.php` | 410 | `final class SmartAutomationPro` uses 24 traits. Singleton. __construct() registers ALL hooks. activate() schedules 6 crons. |
| `includes/ssp-cors.php` | 23 | Early CORS handler for Browser Bridge REST API (OPTIONS preflight). |

### Core Logic Traits
| File | Lines | Trait | Key Functions |
|------|-------|-------|---------------|
| `class-ssp-helpers.php` | 1017 | SSP_Helpers | ajax_require_auth/admin, get/set_user_items, get/set_global_items, get_user_plan, parse_ai_json, log_activity, Jalali dates, SEO analysis, proxy, UTM, 40 methods total |
| `class-ssp-network.php` | 244 | SSP_Network | resolve_doh, wp_remote_with_doh, send_via_relay, test_doh_connectivity, test_basic_connectivity, get_telegram_relay_settings, is_telegram_relay_enabled, test_telegram_relay |
| `class-ssp-ai-api.php` | 257 | SSP_AiApi | call_ai_api (retry on 429), send_ai_request, call_openai_compat, call_anthropic, call_gemini |
| `class-ssp-messenger.php` | 449 | SSP_Messenger | format_message, send_to_messenger, send_whatsapp/instagram/rubika/bot_platform, send_media_group, download_file_for_upload |
| `class-ssp-queue.php` | 386 | SSP_Queue | add_to_queue, process_queue, execute_action, action_publish_content, clean_messenger_text, process_schedules |
| `class-ssp-seo.php` | 1990 | SSP_Seo | 16 SEO tools, 10 prompt modes, handle_site_audit, extract_page_data |
| `class-ssp-bot-builder.php` | 2227 | SSP_BotBuilder | Bot CRUD, bot_api_request, handle_bot_webhook, 12 button types, templates, scenarios, debug functions |
| `class-ssp-wp-publish.php` | 530 | SSP_WpPublish | publish_to_wp_site, generate_ai_image, build_product_payload, 8 product prompt templates |
| `class-ssp-email.php` | 90 | SSP_Email | send_email_notification (4 types), send_daily_summary |
| `class-ssp-cleanup.php` | 123 | SSP_Cleanup | run_cleanup, check_webhook_rate_limit, encrypt/decrypt_token, sanitize_webhook_input |
| `class-ssp-link-shortener.php` | 140 | SSP_LinkShortener | shorten_url (self/Bitly/TinyURL), handle_short_link_redirect |
| `class-ssp-content-distribution.php` | 454 | SSP_ContentDistribution | Distribution rules CRUD, process_distributions, execute_single_distribution, multi-profile support |
| `class-ssp-ai-browser.php` | 415 | SSP_AiBrowserBridge | Token management, bridge task lifecycle, REST callbacks, AJAX handlers, CORS |

### AJAX Handler Traits
| File | Lines | Trait | Handlers |
|------|-------|-------|----------|
| `class-ssp-ajax-general.php` | 576 | SSP_AjaxGeneral | save_settings, test_connection (6 platforms), test_ai, test_ai_live, generate_now, manual_send, upload_media, generate_ai_content, telegram_relay |
| `class-ssp-ajax-messengers.php` | 92 | SSP_AjaxMessengers | get/add/delete/update_messenger, check_health, validate_token |
| `class-ssp-ajax-wp-sites.php` | 103 | SSP_AjaxWpSites | add/delete/update/test_wp_site (supports site_id lookup), fetch_categories |
| `class-ssp-ajax-rss.php` | 123 | SSP_AjaxRss | add/delete/update_rss_feed, fetch_rss_now, process_rss_feeds |
| `class-ssp-ajax-drafts.php` | 52 | SSP_AjaxDrafts | save/get/delete/update_draft |
| `class-ssp-ajax-templates.php` | 32 | SSP_AjaxTemplates | save/get/delete_template_item |
| `class-ssp-ajax-automation.php` | 121 | SSP_AjaxAutomation | add/delete_schedule, add/delete/toggle_trigger, get_calendar, schedule_batch |
| `class-ssp-ajax-admin.php` | 146 | SSP_AjaxAdmin | activate_license (HMAC-SHA256), clear_logs, manual_process, generate/revoke/update_license, activate_cron |
| `class-ssp-ajax-product.php` | 705 | SSP_AjaxProduct | generate_product_ai, brainstorm, bulk_generate, fetch_woo_*, shorten_url, proxy/network/doh settings, email/utm/image settings, seo_ai |
| `class-ssp-ajax-profiles.php` | 162 | SSP_AjaxProfiles | get/add/update/switch/delete_profile, profile item helpers |
| `class-ssp-ajax-prompt-builder.php` | 117 | SSP_AjaxPromptBuilder | save/get/delete_prompt_template, preview_prompt, build_prompt |

### Views & Frontend
| File | Lines | Purpose |
|------|-------|---------|
| `includes/views/admin-panel.php` | 826 | Admin panel: stats, license CRUD, system health, network diagnostics, DoH/relay/proxy settings |
| `includes/views/portal.php` | 14138 | **MAIN UI**: 23 tabs, CSS design system, 327 JS functions, all frontend logic |
| `assets/js/ai-bridge.user.js` | 424 | Tampermonkey userscript (v4.0.0). DeepSeek/ChatGPT automation. Streaming detection. Status widget. |
| `ai-bridge-serve.php` | 61 | Serves userscript with token injection |

---

## 3. ARCHITECTURE

```
main.php
  ├── require_once ssp-cors.php (early CORS)
  ├── require_once constants.php
  ├── require_once 24 trait files
  ├── require_once class-ssp-core.php
  │     └── final class SmartAutomationPro { use 24 traits; }
  ├── register_activation_hook → activate
  ├── register_deactivation_hook → deactivate
  └── plugins_loaded → init()
```

### Key Patterns

**Storage** (NO custom DB tables — hosting blocks CREATE TABLE):
```php
// Per-user: wp_usermeta with ssp_ prefix
$user_data = get_user_meta($user_id, 'ssp_messengers', true);
update_user_meta($user_id, 'ssp_messengers', $user_data);
// Global: wp_options with ssp_ prefix
$licenses = get_option('ssp_licenses', []);
update_option('ssp_licenses', $licenses);
```

**AJAX Handler**:
```php
function handle_xxx() {
    check_ajax_referer('ssp_secure_nonce', 'security');
    $user_id = $this->ajax_require_auth();  // or ajax_require_admin()
    $param = sanitize_text_field($_POST['param'] ?? '');
    // ... business logic ...
    wp_send_json_success(['data' => $result]);
}
```

**Frontend JS**:
```javascript
// Data in JS variables, NOT HTML attributes
var messengerData = <?php echo json_encode($messenger_json); ?>;
// Event delegation on container
document.querySelector('.ssp-container').addEventListener('click', function(e) {
    var target = e.target.closest('[data-action]');
    if (!target) return;
    // ... handle action ...
});
```

---

## 4. SECURITY

- **Nonce**: All AJAX uses `check_ajax_referer('ssp_secure_nonce', 'security')`
- **Auth**: `ajax_require_auth()` (login check) or `ajax_require_admin()` (manage_options)
- **Rate limit**: Webhook 30 req/60s via transient `ssp_rl_{md5(ip)}`
- **SSRF**: URL auditor blocks private IPs (127.x, 10.x, 172.16.x, 192.168.x, 169.254.x)
- **Token encryption**: AES-128-CBC for bot tokens
- **License**: HMAC-SHA256, rate-limited 5/min, format `PRO-XXXX-XXXX-XXXX`
- **Input**: `sanitize_text_field()`, `sanitize_textarea_field()`, `esc_url_raw()`, `intval()`, `sanitize_webhook_input()` (recursive)
- **Data protection**: Entity data in JS vars (not HTML attributes), API keys masked in UI

---

## 5. HOOKS & REGISTRATION

### WP-Cron Events (6)
| Hook | Interval | Delay | Purpose |
|------|----------|-------|---------|
| ssp_process_queue_hook | ssp_two_minutes (300s) | +60s | Process message queue |
| ssp_ai_generation_hook | hourly | +120s | Auto-generate AI content |
| ssp_process_schedules_hook | ssp_two_minutes | +60s | Process scheduled posts |
| ssp_rss_fetch_hook | ssp_two_minutes | +90s | Fetch RSS feeds |
| ssp_daily_summary_hook | daily | +3600s | Send daily email summary |
| ssp_cleanup_hook | daily | +1800s | Cleanup logs/queue/transients |

### REST API Routes (4)
| Route | Method | Purpose |
|-------|--------|---------|
| ssp/v1/bot-webhook/{bot_id} | POST | Bot message handler (public, rate-limited) |
| ssp/v1/ai-bridge/pending | GET | Poll for tasks (token auth) |
| ssp/v1/ai-bridge/response | POST | Submit AI response (token auth) |
| ssp/v1/ai-bridge/status | GET | Check task status (token auth) |

### Shortcode
`[ssp_portal]` — renders portal.php with output buffering, adds body class `ssp-shortcode-page`

---

## 6. STORAGE KEYS

### wp_usermeta (per-user)
| Key | Type | Purpose |
|-----|------|---------|
| ssp_messengers | array | [{id,profile_id,platform,name,token,channel_id,is_active,created_at}] |
| ssp_wp_sites | array | [{id,profile_id,site_name,site_url,username,app_password,is_active,auto_publish,post_type,categories}] |
| ssp_rss_feeds | array | [{id,profile_id,feed_name,feed_url,is_active,auto_fetch}] |
| ssp_drafts | array | [{id,title,content,hashtags,meta_description,draft_type,status,created_at,updated_at}] |
| ssp_template_items | array | [{id,profile_id,name,category,content,hashtags,signature,usage_count}] |
| ssp_schedules | array | [{id,title,message,scheduled_at,status,recurring}] |
| ssp_triggers | array | [{id,keyword,response,platform,is_active,match_count}] |
| ssp_bot_configs | array | [{id,name,platform,token,is_active,welcome_message,webhook_url,commands[],buttons[],auto_replies[]}] |
| ssp_distributions | array | [{id,name,only_new,source_site_id,source_type,filter_categories,filter_tags,filter_min_price,filter_max_price,sort_order,schedule_times,new_delay_value,new_delay_unit,target_messengers,daily_limit,message_template,include_image,is_active}] |
| ssp_profiles | array | Business/workspace profiles |
| ssp_prompt_templates | array | Saved prompt templates |
| ssp_product_prompts | array | Custom AI prompts per category |
| ssp_product_templates | array | Product/post templates |
| ssp_link_settings | array | {enabled,provider,api_key} |
| ssp_utm_settings | array | {enabled,source,medium,campaign,auto_source} |
| ssp_email_settings | array | {enabled,custom_email,on_failure,on_license_expiry,daily_summary} |
| ssp_image_settings | array | {auto_image,default_size,default_quality} |
| ssp_bridge_token | string | Browser bridge token (ssp_bridge_ + 32 chars) |
| ssp_bridge_active_tasks | array | Active bridge task IDs |
| ssp_active_profile_id | int | Currently active profile ID |
| ssp_custom_domain | string | Custom short link domain |
| ssp_plan | string | 'free' or 'pro' |
| ssp_plan_expiry | int | Unix timestamp |
| ssp_ai_provider/api_key/model/mode/chatbot | string | AI config |
| ssp_ai_rewrite/hashtags | int | 0/1 flags |
| ssp_ai_prompt_mode/custom_prompt | string | Prompt config |
| ssp_msg_template/signature/global_hashtags | string | Message config |
| ssp_auto_wp_posts/ai_auto_generate | int | 0/1 flags |
| ssp_publish_history | array | [{type,title,site,post_id,url}] (max 100) |
| ssp_generated_images | array | AI image history (max 20) |
| ssp_dist_history_{id} | array | Distribution history (max 2000, 90 days) |
| ssp_default_template_id | int | Default message template ID for Manual Send |

### wp_options (global)
| Key | Type | Purpose |
|-----|------|---------|
| ssp_logs | array | Activity log (max 200) |
| ssp_global_items | array | Contains 'queue' key |
| ssp_licenses | array | [{id,license_key,signature,plan,duration_days,used_by}] |
| ssp_license_secret | string | HMAC secret |
| ssp_short_links | array | [{code,url,user_id,clicks}] |
| ssp_proxy_settings | array | {enabled,host,port,type,username,password} |
| ssp_doh_enabled | int | DoH toggle |
| ssp_doh_server | string | DoH server |
| ssp_telegram_relay | array | {enabled,worker_url,secret_key} |
| ssp_encryption_key | string | AES key |
| ssp_bridge_rewrite_version | string | Current: '3.0' |
| ssp_style_cleanup_pending/version | string | Style cleanup tracking |

### Transients
```
ssp_bridge_task_{task_id}    - Task data (5 min TTL)
ssp_bridge_result_{task_id}  - Result data (5 min TTL)
```

---

## 7. CONSTANTS

```php
SSP_VERSION = '15.0.0'
SSP_CRON_INTERVAL = 5 * MINUTE_IN_SECONDS     // 300s
SSP_MSG_DELAY = 1.5                            // seconds between sends
SSP_WP_POST_DELAY = 3                          // seconds between WP posts
SSP_AI_RETRY_DELAY = 5                         // retry delay multiplier
SSP_MAX_DRAFTS = 50
SSP_MAX_TEMPLATES = 20
SSP_META_DESC_LENGTH = 155
SSP_MAX_HASHTAGS = 10
SSP_WHATSAPP_API_VERSION = 'v18.0'
SSP_BOT_BUILDER_MAX_COMMANDS = 20
SSP_BOT_BUILDER_MAX_BUTTONS = 10
SSP_BOT_BUILDER_MAX_CHILD_BUTTONS = 8
SSP_BOT_BUILDER_MAX_AUTO_REPLIES = 50
SSP_BOT_BUILDER_MAX_SCENARIO_STEPS = 10
SSP_BOT_BUILDER_MAX_SCENARIO_BUTTONS = 4
SSP_BOT_HEALTH_CHECK_INTERVAL = 3600
SSP_BOT_RETRY_MAX_ATTEMPTS = 3
SSP_BOT_RETRY_BASE_DELAY = 2
SSP_MAX_LOGS = 200
SSP_LOG_CLEANUP_INTERVAL = 86400
SSP_WEBHOOK_RATE_LIMIT = 30
SSP_WEBHOOK_TIMEOUT = 5
SSP_AI_CACHE_TTL = HOUR_IN_SECONDS
SSP_BOT_CONFIG_CACHE_TTL = 300
SSP_MAX_BOTS_PER_USER = 5
SSP_TOKEN_ENCRYPTION_KEY = 'ssp_bot_'
SSP_BROWSER_BRIDGE_TASK_TTL = 5 * MINUTE_IN_SECONDS
SSP_BROWSER_BRIDGE_POLL_INTERVAL = 3
SSP_BROWSER_BRIDGE_MAX_WAIT = 5 * MINUTE_IN_SECONDS
SSP_RELAY_DEFAULT_TIMEOUT = 30
SSP_DOH_SERVERS = [
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
]
```

---

## 8. EXTERNAL APIs

| Service | Endpoint | Auth |
|---------|----------|------|
| Telegram Bot API | api.telegram.org/bot{token}/{method} | Token in URL |
| Bale Bot API | tapi.bale.ai/bot{token}/{method} | Token in URL |
| Eitaa API | eitaayar.ir/api/{token}/{method} | Token in URL |
| Rubika Bot API | botapi.rubika.ir/v3/{token}/{method} | Token in URL |
| WhatsApp Cloud API | graph.facebook.com/v18.0/{phone_id}/messages | Bearer token |
| Instagram Graph API | graph.facebook.com/v18.0/{ig_user_id}/media | Access token |
| OpenAI API | api.openai.com/v1/chat/completions | Bearer token |
| OpenAI DALL-E 3 | api.openai.com/v1/images/generations | Bearer token |
| OpenRouter API | openrouter.ai/api/v1/chat/completions | Bearer token |
| Anthropic API | api.anthropic.com/v1/messages | x-api-key |
| Gemini API | generativelanguage.googleapis.com/v1beta/models/{model}:generateContent | x-goog-api-key |
| Groq API | api.groq.com/openai/v1/chat/completions | Bearer token |
| DeepSeek API | api.deepseek.com/chat/completions | Bearer token |
| Bitly API | api-ssl.bitly.com/v4/shorten | Bearer token |
| TinyURL API | tinyurl.com/api-create.php | None |
| WooCommerce REST API | {site}/wp-json/wc/v3/products | Basic auth |
| WordPress REST API | {site}/wp-json/wp/v2/{type} | Basic auth |
| DNS-over-HTTPS | 12 providers (see SSP_DOH_SERVERS) | None |

---

## 9. MESSENGER PLATFORMS

| Platform | API Base | parse_mode | Media | Gallery |
|----------|----------|------------|-------|---------|
| Telegram | api.telegram.org/bot{token} | HTML | URL (sendPhoto/sendVideo) | sendMediaGroup (up to 10) |
| Bale | tapi.bale.ai/bot{token} | HTML | URL (same as Telegram) | sendMediaGroup (up to 10) |
| Eitaa | eitaayar.ir/api/{token} | NONE | CURLFile upload (raw curl_exec) | Single only |
| Rubika | botapi.rubika.ir/v3/{token} | NONE | 3-step CURLFile upload | Single only |
| WhatsApp | graph.facebook.com/v18.0/{phone_id}/messages | NONE | URL in JSON | Single only |
| Instagram | graph.facebook.com/v18.0/{ig_user_id}/media | NONE | 2-step URL publish | Single only |

### Critical Pitfalls
1. **Eitaa & Rubika**: `wp_remote_post` with CURLFile fails — always use raw `curl_exec`
2. **Rubika**: Response uses `data` not `result`; chat_id MUST be string
3. **Telegram in Iran**: api.telegram.org blocked — use Relay or DoH fallback
4. **Albums**: Only Telegram/Bale support `sendMediaGroup`
5. **Video detection**: `is_video_url()` checks extension, not MIME type

### Telegram Fallback Chain
```
1. Relay (self-hosted PHP worker) → 2. DoH (resolve to IP) → 3. Direct/Proxy
```

### Detailed Messenger API Documentation

#### Eitaa (ایتا) — Single Image Only, CURLFile Upload
```
Base URL: https://eitaayar.ir/api/{token}

SEND TEXT:
POST /sendMessage
Content-Type: application/x-www-form-urlencoded
Body: chat_id={channel_id}&text={message}
Response: {"ok":true,"result":{"message_id":123}}

SEND IMAGE/VIDEO (single only):
POST /sendFile
Content-Type: multipart/form-data
Fields:
  file: CURLFile({temp_path}, {mime_type}, {filename})
  chat_id: {channel_id}
  caption: {message}
  title: ""  ← REQUIRED empty string
Response: {"ok":true,"result":{"message_id":123}}

CRITICAL:
- wp_remote_post with CURLFile FAILS — must use raw curl_exec
- parse_mode: NONE (no HTML/Markdown, plain text only)
- gallery/album: NOT supported (single image/video per message)
- If sendFile fails → falls back to text-only sendMessage
```

#### Rubika (روبیکا) — 3-Step CURLFile Upload, Single Only
```
Base URL: https://botapi.rubika.ir/v3/{token}

STEP 1: Request upload URL
POST /requestSendFile
Content-Type: application/json
Body: {"type":"Image"}  or  {"type":"Video"}
Response: {"status":"OK","data":{"upload_url":"https://..."}}

STEP 2: Upload file (raw curl_exec, NOT wp_remote_post)
POST {upload_url}
Content-Type: multipart/form-data
Field: file = CURLFile({temp_path}, {mime_type}, {filename})
Response: {"status":"OK","data":{"file_id":"..."}}
- file_id extracted from: data.file_id → result.file_id → file_id (fallback chain)

STEP 3: Send file with caption
POST /sendFile
Content-Type: application/json
Body: {"chat_id":"{channel_id_as_string}","file_id":"{file_id}","text":"{message}"}
Response: {"status":"OK","data":{"message_id":"..."}}

TEXT-ONLY FALLBACK (if sendFile pipeline fails):
POST /sendMessage
Content-Type: application/json
Body: {"chat_id":"{channel_id_as_string}","text":"{message}"}
If JSON fails → retry with form-encoded body

CRITICAL:
- chat_id MUST be cast to (string) — Rubika rejects numeric types
- Response format: {"status":"OK","data":{...}} — NOT {"ok":true,"result":{...}}
- wp_remote_post with CURLFile FAILS — must use raw curl_exec
- gallery/album: NOT supported (single image/video per message)
- parse_mode: NONE (plain text only)
```

#### Telegram/Bale — URL-Based Media, Gallery Support
```
Telegram: https://api.telegram.org/bot{token}/{method}
Bale:     https://tapi.bale.ai/bot{token}/{method}

SEND TEXT:
POST /sendMessage
Body: {chat_id, text, parse_mode: "HTML"}

SEND PHOTO:
POST /sendPhoto
Body: {chat_id, photo: "{image_url}", caption: "{message}", parse_mode: "HTML"}

SEND VIDEO:
POST /sendVideo
Body: {chat_id, video: "{image_url}", caption: "{message}", parse_mode: "HTML"}

SEND GALLERY (up to 10 items):
POST /sendMediaGroup
Content-Type: application/json
Body: {
  "chat_id": "{channel_id}",
  "media": [
    {"type":"photo","media":"url1","caption":"{message}"},
    {"type":"photo","media":"url2"},
    {"type":"video","media":"url3"}
  ]
}
- caption only on FIRST item
- timeout: 60s (double normal 30s)
- supported platforms: Telegram, Bale ONLY

CRITICAL:
- parse_mode: "HTML" — supports <b>, <i>, <a>, etc.
- media URLs sent directly (no download needed)
- Telegram in Iran: api.telegram.org blocked — use Relay or DoH
```

#### WhatsApp — JSON URL-Based
```
Base: https://graph.facebook.com/v18.0/{phone_number_id}/messages
Auth: Authorization: Bearer {token}

SEND TEXT:
Body: {"messaging_product":"whatsapp","to":"{phone_number_id}","type":"text","text":{"body":"{message}"}}

SEND IMAGE:
Body: {"messaging_product":"whatsapp","to":"{phone_number_id}","type":"image","image":{"link":"{image_url}","caption":"{message}"}}

SEND VIDEO:
Body: {"messaging_product":"whatsapp","to":"{phone_number_id}","type":"video","video":{"link":"{image_url}","caption":"{message}"}}

CRITICAL:
- messaging_product: "whatsapp" field REQUIRED in all requests
- gallery/album: NOT supported
- parse_mode: NONE (plain text)
```

#### Instagram — 2-Step Publish
```
Base: https://graph.facebook.com/v18.0/{ig_user_id}

STEP 1: Create container
POST /media
Body: {image_url, caption: "{message}", access_token: "{token}"}
Response: {id: "{creation_id}"}

STEP 2: Publish
POST /media_publish
Body: {creation_id: "{creation_id}", access_token: "{token}"}
Response: {id: "{media_id}"}

CRITICAL:
- Image REQUIRED for every post (text-only not supported)
- gallery/album: NOT supported
```

#### format_message() Placeholders
```
Template: {title}\n\n{message}\n\n{hashtags}\n\n{link}\n\n{signature}
- {title}      → post title
- {message}    → post content
- {link}       → URL with UTM params appended
- {hashtags}   → passed hashtags + global hashtags (concatenated)
- {signature}  → user's signature line
```

#### is_video_url() Detection
```
Extensions checked: mp4, mpeg, mpg, mov, avi, webm
Method: pathinfo($url, PATHINFO_EXTENSION) — checks extension, NOT MIME type
```

#### download_file_for_upload() Flow
```
1. wp_remote_get($url) with proxy args → save to temp
2. MIME detection: Content-Type header → extension map → 'application/octet-stream'
3. Filename: basename from URL → fallback 'upload_{timestamp}.{ext}'
4. Save to: sys_get_temp_dir() . '/ssp_' . wp_generate_password(8) . $ext
5. Returns: ['path' => $tmp, 'mime' => $mime, 'name' => $name]
6. Used by: Rubika (step 2), Eitaa (sendFile)
```

---

## 10. BOT BUILDER

### Button Types (12)
| Type | Keyboard | Key Fields |
|------|----------|------------|
| simple | reply | text, value |
| url | inline | text, value (URL) |
| callback | inline | text, value, requires_password |
| web_app | inline | text, value (URL) |
| login_url | inline | text, value, bot_username, forward_text |
| switch_inline | inline | text, value (query), switch_to_chat |
| request_contact | reply | text |
| request_location | reply | text |
| request_poll | reply | text |
| request_peer | reply | text, peer_type, max_quantity, name/username/photo_requested |
| pay | inline | text |

### Webhook Flow
```
POST /wp-json/ssp/v1/bot-webhook/{bot_id}
→ rate limit (30/60s) → sanitize → find bot owner → cache config
→ /start → welcome | /command → exact match | text → auto_replies (exact→contains→starts_with) | callback → buttons
```

### Debug & Cleanup
- `handle_debug_bot_list()` — lists all bots (up to 1000)
- `handle_debug_bot_buttons()` — raw button data for debugging
- `cleanup_all_bot_buttons_style()` — one-time migration removing `style` field

---

## 11. NETWORK LAYER

### DNS-over-HTTPS Flow
```
resolve_doh($domain) → try selected server → iterate others → GET Accept: application/dns-json
→ parse Answer type=1 (A record) → return IP
```

### DoH Request Flow
```
wp_remote_with_doh($url, $args, $doh_server, $method)
→ resolve_doh(host) → rewrite URL to IP + Host header → sslverify=false → request
```

### Telegram Relay Flow
```
send_via_relay($method, $data) → load ssp_telegram_relay → build body
→ DNS: gethostbyname() → DoH fallback → rewrite → proxy → direct
```

---

## 12. SEO TOOLS (16)

| # | Tool | AJAX Action |
|---|------|-------------|
| 1 | Content SEO | ssp_analyze_seo |
| 2 | Meta Description | ssp_generate_meta_desc |
| 3 | Title Suggestions | ssp_seo_suggest_title |
| 4 | AI SEO Analysis | ssp_analyze_seo_ai |
| 5 | Keyword Analyzer | ssp_keyword_analyzer |
| 6 | SERP Preview | ssp_serp_preview |
| 7 | URL Auditor | ssp_url_auditor |
| 8 | GEO Analysis | ssp_geo_analyze |
| 9 | Schema Generator | ssp_schema_generator |
| 10 | E-E-A-T | ssp_eeat_analyze |
| 11 | Content Gap | ssp_content_gap |
| 12 | Topic Cluster | ssp_topic_cluster |
| 13 | SEO Checklist | ssp_seo_checklist |
| 14 | Featured Snippet | ssp_featured_snippet |
| 15 | Voice Search | ssp_voice_search |
| 16 | Site Audit | ssp_site_audit |

### AI Prompt Modes (SSP_Seo::$prompt_modes)
`human`, `redteam`, `x10think`, `socrates`, `truth`, `meta`, `predict`, `ooda`, `eli10`, `alt3`

---

## 13. UI TABS (23)

| # | Tab ID | English | Description |
|---|--------|---------|-------------|
| 1 | dashboard | Dashboard | Stats, quickstart, recent activity |
| 2 | messengers | Messengers | Messenger list + CRUD |
| 3 | botbuilder | Bot Builder | Bot CRUD, commands, buttons, auto-replies |
| 4 | manual | Manual Send | Multi-messenger send, file upload, schedule |
| 5 | drafts | Drafts | Draft list + CRUD |
| 6 | template | Templates | Message templates (max 20) |
| 7 | generate | Content Center | Tool cards grid, batch generation |
| 8 | brainstorm | Brainstorm | AI idea generation |
| 9 | postgen | Post Generator | AI post generation (API/bridge) |
| 10 | contentgen | Article Generator | Long-form article generation |
| 11 | productgen | Product Generator | WooCommerce products, bulk publish |
| 12 | promptbuilder | Prompt Builder | Custom AI prompt templates (max 20) |
| 13 | calendar | Content Calendar | Scheduled posts calendar |
| 14 | schedules | Scheduling | Schedule management |
| 15 | sources | RSS Feeds | RSS feed management (max 10/profile) |
| 16 | wpsources | WP Sources | WordPress site connections |
| 17 | triggers | Triggers | Keyword auto-replies |
| 18 | distribution | Auto Distribution | Content distribution rules |
| 19 | reports | Reports | Activity reports |
| 20 | seo | SEO Tools | 16 SEO tools with sub-tabs |
| 21 | links | Link Shortener | URL shortening + click tracking |
| 22 | ai | AI Settings | Provider, API key, model, browser bridge |
| 23 | subscription | Subscription | License activation, plan info |

### SEO Sub-Tabs
`seo-subtab-content`, `seo-subtab-keywords`, `seo-subtab-serp`, `seo-subtab-geo`, `seo-subtab-eeat`, `seo-subtab-schema`, `seo-subtab-site-audit`

---

## 14. CSS DESIGN

### Theme Variables
```css
:root {
  --ssp-primary: #6366f1; --ssp-bg: #f8fafc; --ssp-surface: #ffffff;
  --ssp-text: #1e293b; --ssp-text-secondary: #64748b;
  --ssp-border: #e2e8f0; --ssp-success: #22c55e; --ssp-warning: #f59e0b; --ssp-danger: #ef4444;
  --ssp-sidebar-width: 260px; --ssp-header-height: 64px;
}
[data-theme="dark"] {
  --ssp-bg: #0f172a; --ssp-surface: #1e293b;
  --ssp-text: #f1f5f9; --ssp-text-secondary: #94a3b8; --ssp-border: #334155;
}
```

### Component Classes
`.ssp-header`, `.ssp-sidebar`, `.ssp-content`, `.ssp-tab-btn`/`.ssp-tab-content`, `.ssp-card`, `.ssp-btn-primary`/`secondary`/`test`/`danger`, `.ssp-toggle`, `.ssp-badge`, `.ssp-table-wrap`, `.ssp-modal`, `.ssp-calendar`, `.ssp-seo-score`, `.ssp-fab` (mobile), `.ssp-drawer` (mobile)

### Responsive
- Desktop: > 768px (sidebar visible)
- Mobile: <= 768px (sidebar hidden, FAB + slide-out drawer)

---

## 15. MODAL OVERLAYS

| Modal ID | Purpose |
|----------|---------|
| modal_edit_messenger | Edit messenger form |
| modal_edit_wpsite | Edit WordPress site form |
| modal_edit_rss | Edit RSS feed form |
| modal_onboarding | 3-step onboarding wizard |
| modal_profiles | Profile management |
| modal_prompt_builder | Prompt template builder |

---

## 16. AJAX ACTIONS (~141)

### General
ssp_save_settings, ssp_test_connection, ssp_test_ai, ssp_test_ai_live, ssp_activate_license, ssp_clear_logs, ssp_manual_process, ssp_generate_now, ssp_generate_ai_content, ssp_manual_send, ssp_upload_media

### Messengers
ssp_add_messenger, ssp_delete_messenger, ssp_update_messenger, ssp_get_messengers, ssp_check_messenger_health, ssp_validate_messenger_token

### WP Sites
ssp_add_wp_site, ssp_delete_wp_site, ssp_update_wp_site, ssp_test_wp_site, ssp_fetch_wp_categories

### RSS / Drafts / Templates
ssp_add/delete/update_rss_feed, ssp_fetch_rss_now, ssp_save/get/delete/update_draft, ssp_save/get/delete_template_item

### Automation / Calendar
ssp_add/delete/schedule_batch_schedule, ssp_add/delete/toggle_trigger, ssp_get_calendar, ssp_update_schedule_time

### Admin
ssp_generate_license, ssp_revoke_license, ssp_update_license_expiry, ssp_activate_cron

### Bot Builder
ssp_save/delete/get_bot_config, ssp_add/delete/update_bot_command, ssp_add/delete/update_bot_button, ssp_add/delete/update_bot_auto_reply, ssp_set_bot_welcome, ssp_set_bot_menu, ssp_test/set_bot_webhook, ssp_get_bot_stats, ssp_generate_bot_code, ssp_debug_bot_list, ssp_debug_bot_buttons, ssp_load/apply_bot_template, ssp_save/delete_bot_scenario

### Product / Batch / Links
ssp_generate_product, ssp_generate_product_ai, ssp_upload_product_image, ssp_upload_post_image, ssp_brainstorm_ideas, ssp_fetch_woo_categories/brands/shipping, ssp_save/get/delete_product_template, ssp_save_product_prompt, ssp_bulk_generate_products, ssp_get/clear_publish_history, ssp_fetch_remote_product, ssp_batch_generate, ssp_send_batch, ssp_shorten_url, ssp_save/get/delete_link_settings, ssp_save_short_url_format, ssp_save_custom_domain

### Network / Settings
ssp_save_proxy_settings, ssp_test_proxy, ssp_network_diagnostics, ssp_save_doh_settings, ssp_test_doh, ssp_test_telegram_dns, ssp_save/test_telegram_relay, ssp_save_utm_settings, ssp_save/test_email_settings, ssp_generate_image, ssp_save_image_settings

### Profiles / Prompt Builder / Distribution
ssp_get/add/update/switch/delete_profile, ssp_save/get/delete_prompt_template, ssp_preview_prompt, ssp_get/save/delete/toggle/preview/run_distribution_now

### SEO (16)
ssp_analyze_seo, ssp_analyze_seo_ai, ssp_generate_meta_desc, ssp_seo_suggest_title, ssp_keyword_analyzer, ssp_serp_preview, ssp_url_auditor, ssp_geo_analyze, ssp_schema_generator, ssp_eeat_analyze, ssp_content_gap, ssp_topic_cluster, ssp_seo_checklist, ssp_featured_snippet, ssp_voice_search, ssp_site_audit

### Browser Bridge
ssp_bridge_setup, ssp_bridge_create_task, ssp_bridge_poll_status, ssp_bridge_cancel_task

---

## 17. KEY JS FUNCTIONS

### Navigation & Core
`switchTab()`, `toggleTheme()`, `switchProfile()`, `showToast()`, `setLoading()`

### Messengers
`addMessenger()`, `deleteMessenger()`, `updateMessenger()`, `testConnection()`

### Content Generation
`manualSend()`, `saveDraft()`, `pgGeneratePost()`, `pgGeneratePostViaBrowser()`, `pgSendPost()`, `pgConfirmSchedule()`, `bsGenerate()`, `bsGenerateViaBrowser()`, `cgBatchGenerate()`

### Batch Content (tab-generate)
`batchGenerate()`, `renderBatchResults()`, `sendBatchItem()`, `confirmSendBatchItem()`, `saveBatchItemAsDraft()`, `sendBatch()`, `saveBatchAsDrafts()`, `showScheduleBatchModal()`, `cleanCitations()`, `openJalaliPicker()`

### Product/WooCommerce
`cgSiteChanged()`, `pgBulkPublish()`, `pgLoadBrands()`, `pgLoadShippingClasses()`, `pgCloneFetch()`, `pgSaveAsTemplate()`, `pgLoadTemplates()`, `pgLoadHistory()`

### Bot Builder
`saveBotConfig()`, `addBotCommand()`, `addBotButton()`, `addBotAutoReply()`, `setBotWebhook()`, `testBotWebhook()`, `generateBotCode()`

### Browser Bridge
`bridgeSetup()`, `bridgeCreateTask()`, `bridgePollStatus()`, `bridgeCancelTask()`

---

## 18. MODIFICATION GUIDE

### New Messenger Platform
1. `class-ssp-messenger.php` — new `send_{platform}()` method
2. `send_to_messenger()` dispatcher
3. `class-ssp-ajax-general.php` — `handle_test_connection()`
4. portal.php — platform option in add form
5. `is_video_url()` — add if supports video

### New AI Provider
1. `class-ssp-ai-api.php` — case in `send_ai_request()` + new `call_{provider}()`
2. `handle_test_ai()` and `handle_test_ai_live()` in ajax-general.php
3. portal.php — provider option

### New AJAX Handler
1. Method in appropriate trait file
2. Register in core.php: `add_action('wp_ajax_{action}', [$this, 'handle_{action}'])`
3. JS function in portal.php
4. Start with `ajax_require_auth()` or `ajax_require_admin()`

### New Bot Button Type
1. Case in `handle_add_bot_button()` + `handle_update_bot_button()` (bot-builder.php)
2. Case in `handle_bot_webhook()` if needed
3. portal.php bot builder UI

### New SEO Tool
1. Method in `class-ssp-seo.php`
2. AJAX handler + register in core.php
3. JS + UI in portal.php SEO section

### New Cron Event
1. Register in core.php `__construct()`
2. Schedule in `activate()`: `wp_schedule_event(time() + delay, 'ssp_two_minutes', '{hook}')`
3. Clear in `deactivate()`: `wp_clear_scheduled_hook('{hook}')`

---

## 19. COMMON PATTERNS

### Storage CRUD
```php
$items = $this->get_user_items($user_id, 'messengers');   // Read
$this->set_user_items($user_id, 'messengers', $items);    // Write
$items = $this->get_global_items('logs');                  // Read global
$this->set_global_items('logs', $items);                   // Write global
$id = $this->next_id($items);                              // Next ID
```

### AI API Call
```php
$result = $this->call_ai_api($provider, $api_key, $model, $prompt, $json_mode);
// Returns: ['content' => string, 'tokens_used' => int, 'cost' => float|null]
```

### Network Request (DoH + Proxy)
```php
$proxy_args = $this->get_proxy_args();
if ($doh_enabled && empty($proxy_args)) {
    $response = $this->wp_remote_with_doh($url, $args, $doh_server, 'POST');
} else {
    $response = wp_remote_post($url, array_merge($args, $proxy_args));
}
```

---

## 20. DATA STRUCTURES

```php
// Messenger Config
['id' => 1, 'profile_id' => 1, 'platform' => 'telegram', 'name' => 'My Channel',
 'token' => '123456:ABC...', 'channel_id' => '-100123456', 'is_active' => 1]

// Bot Config
['id' => 1, 'name' => 'My Bot', 'platform' => 'telegram', 'token' => '...',
 'welcome_message' => 'Welcome!', 'webhook_url' => '',
 'commands' => [...], 'buttons' => [...], 'auto_replies' => [...]]

// Queue Item
['id' => 1, 'user_id' => 1, 'action_type' => 'manual_send',
 'payload' => ['title' => '...', 'message' => '...', 'hashtags' => '...', 'image_url' => '...', 'selected_messengers' => [1,2]],
 'priority' => 10, 'status' => 'pending', 'attempts' => 0, 'max_attempts' => 3]

// Log Entry
['id' => 1, 'user_id' => 1, 'platform' => 'telegram', 'title' => '...',
 'status' => 'success', 'ai_processed' => 1, 'ai_provider' => 'openai', 'ai_tokens_used' => 150]

// Distribution Rule
['id' => 1, 'name' => 'Daily Products', 'only_new' => 1, 'source_type' => 'product',
 'source_site_id' => 1, 'filter_categories' => '15,23', 'filter_tags' => '8',
 'filter_min_price' => 100000, 'filter_max_price' => 5000000,
 'sort_order' => 'newest', 'new_delay_value' => 5, 'new_delay_unit' => 'minutes',
 'message_template' => "{title}\n{excerpt}\n{url}", 'target_messengers' => '1,2',
 'include_image' => 1, 'schedule_times' => '09:00,15:00', 'daily_limit' => 2,
 'is_active' => 1]
```

---

## 21. PLUGIN LIFECYCLE

**Activation**: Schedule 6 crons → register bridge rewrite → flush rewrite → update version → style cleanup
**Deactivation**: Clear all 6 scheduled hooks
**Admin Menu**: `dashicons-superhero-alt` → "اتوماسیون من" (portal.php) → "پنل مدیریت" (admin-panel.php)
**Shortcode**: `[ssp_portal]` → output buffered portal.php

---

## 22. PROFILE SYSTEM (Business Workspaces)

### Overview
Users can create multiple profiles (businesses/brands) and switch between them. Each profile has its own messengers, WP sites, RSS feeds, schedules, triggers, and product prompts. Profile switcher in header with manage button.

### Profile Data Structure
```php
[
    'id'         => 1,                    // int, unique per user, 1 = default
    'name'       => 'پیش‌فرض',            // string, display name
    'color'      => '#4F46E5',            // string, hex color for UI
    'is_active'  => true,                 // bool, exactly one is active at any time
    'created_at' => '2026-07-25 12:00:00' // string, MySQL datetime
]
```
**Storage**: `ssp_profiles` in `wp_usermeta`
**Limits**: Max 20 profiles per user, Pro plan only
**Default**: ID=1 is always created automatically, cannot be deleted

### Profile CRUD Operations

#### handle_get_profiles()
- Auto-creates default profile (ID=1, name "پیش‌فرض", color #4F46E5) if none exist
- Returns all profiles for the user

#### handle_add_profile() — Pro only
- Parameters: `name` (optional, default "پروفایل جدید"), `color` (optional)
- Auto-color: cycles through 8 predefined colors based on profile count
- New profile is always **inactive** — user must explicitly switch

#### handle_switch_profile()
- Sets selected profile `is_active = true`, all others `is_active = false`
- Frontend does `location.reload()` to re-render with new profile's data

#### handle_delete_profile()
- **ID=1 cannot be deleted** (hard guard)
- If deleted profile was active → first remaining profile becomes active
- Calls `remove_items_by_profile()` to cascade-delete associated items

#### handle_update_profile()
- Only `name` and `color` can be updated (partial updates supported)

### Profile-Filtered Getters (helpers.php)
```php
get_profile_messengers($user_id, $profile_id)  // filters ssp_messengers by profile_id
get_profile_wp_sites($user_id, $profile_id)    // filters ssp_wp_sites by profile_id
get_profile_rss_feeds($user_id, $profile_id)   // filters ssp_rss_feeds by profile_id
// All use same pattern: load all → filter by (int)($item['profile_id'] ?? 1) === $profile_id
// Items without profile_id default to profile 1 (legacy migration)
```

### Profile-Scoped CRUD (profiles.php trait)
```php
get_profile_items($user_id, $key, $profile_id)   // generic filter for any storage key
set_profile_item($user_id, $key, $item, $profile_id)  // upsert: update or append with profile_id stamp
delete_profile_item($user_id, $key, $item_id, $profile_id)  // delete by id + profile_id dual-key
```

### Cascading Delete
When a profile is deleted, `remove_items_by_profile()` removes ALL associated items across these storage keys:
- `messengers`, `wp_sites`, `rss_feeds`, `schedules`, `triggers`, `product_prompt_templates`, `template_items`, `distributions`

### Legacy Migration
`ensure_profile_fields()` runs on every portal page load:
- Scans 6 storage keys (messengers, wp_sites, rss_feeds, schedules, triggers, template_items)
- Any item missing `profile_id` gets stamped with `profile_id = 1`
- Called before any profile-scoped queries

### Auto-Color Logic
```php
$colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];
'color' => $colors[count($profiles) % count($colors)]  // cycles through 8 colors
```

### How profile_id Flows Through CRUD
- **Add messenger/wp_site/rss_feed**: Gets active `profile_id` → stamps item → saves
- **Get messenger/wp_site/rss_feed**: Uses `get_profile_messengers()` etc. (profile-filtered)
- **Delete/Update**: Operates on global array (IDs are globally unique per user, so safe)
- **Distributions**: Uses `get_profile_items()`/`delete_profile_item()` consistently

### AJAX Actions
```
ssp_get_profiles      → handle_get_profiles      (auto-creates default)
ssp_add_profile       → handle_add_profile       (Pro only, max 20)
ssp_update_profile    → handle_update_profile    (name/color only)
ssp_switch_profile    → handle_switch_profile    (sets one active)
ssp_delete_profile    → handle_delete_profile    (not ID=1, cascading delete)
```

### Key Observations
- Profile switch triggers full page reload (`location.reload()`)
- Default profile (ID=1) is created in 3 places: get_profiles, add_profile, get_active_profile_id
- `ensure_profile_fields()` now includes `template_items` for legacy migration. `product_prompt_templates` excluded from migration.
- Delete/update on messengers/wp_sites/rss_feeds operate on global array, not profile-scoped — safe because IDs are globally unique
- `remove_items_by_profile()` also deletes `distributions` for cascading cleanup

---

## 23. BROWSER BRIDGE — COMPLETE REFERENCE

### Overview
The Browser Bridge allows users to use chatbot web UIs (DeepSeek, ChatGPT) instead of API calls.
Works via a Tampermonkey/SnapMonkey userscript that communicates with the plugin through REST API endpoints.

### Complete Flow
```
1. User clicks "Generate" in portal (e.g., cgGenerateViaBrowser)
2. JS builds prompt with CRITICAL RULES requesting JSON-only response
3. AJAX ssp_bridge_create_task → stores transient ssp_bridge_task_{id}
4. JS opens chatbot tab (DeepSeek/ChatGPT) via window.open()
5. Userscript (ai-bridge.user.js) polls GET /wp-json/ssp/v1/ai-bridge/pending every 3s
6. Userscript finds pending task, marks as 'sent'
7. Userscript: waitForElement(input) → setInputValue(prompt) → sendViaEnter()
8. AI generates response on chatbot page
9. Userscript: waitForResponse() — polls every 1s, checks content stability
10. Stability algorithm: response >100 chars, unchanged for 8 consecutive seconds (±10 char tolerance)
11. Userscript POSTs response to /wp-json/ssp/v1/ai-bridge/response
12. Plugin stores result in transient ssp_bridge_result_{id}
13. Portal JS polls AJAX ssp_bridge_poll_status every 3s
14. On completion: closeBridgeModal() → dispatch 'bridge-response' event → fill form fields
```

### Token Management
```php
// Token format: 'ssp_bridge_' + 32 char password
$token = 'ssp_bridge_' . wp_generate_password(32, false);
// Stored in usermeta: ssp_bridge_token
// Validated via direct SQL query: SELECT user_id FROM wp_usermeta WHERE meta_key='ssp_bridge_token' AND meta_value=%s
// Returns integer user_id or 0 (NOT simple string comparison)
```

### Task Lifecycle
```php
$task_data = [
    'task_id'    => 'br_' . wp_generate_password(16, false),
    'user_id'    => $user_id,
    'prompt'     => $prompt,           // max 10000 chars
    'context'    => ['type' => 'contentgen'],  // contentgen|productgen|postgen|seo|brainstorm|batchgen
    'status'     => 'pending',         // pending → sent → completed (or error/timeout)
    'created_at' => current_time('mysql'),
];
// Stored as transient: ssp_bridge_task_{task_id} with TTL=300s (5 min)
// Active task index: usermeta ssp_bridge_active_tasks (array of task IDs)
// Stale detection: tasks pending >120s auto-cancelled
// Force mode: bypasses pending task check
```

### REST API Endpoints (3)

#### GET /wp-json/ssp/v1/ai-bridge/pending
```
Auth: X-SSP-Bridge-Token header
Response (found): { task_id, prompt, context }
Response (none): { status: "none" }
Side effect: transitions task status from 'pending' to 'sent'
```

#### POST /wp-json/ssp/v1/ai-bridge/response
```
Auth: X-SSP-Bridge-Token header
Body: { task_id, response_text, status }
Stores result in: transient ssp_bridge_result_{task_id} (5 min TTL)
Updates task status to: 'completed'
Cleanup: removes task from active index
```

#### GET /wp-json/ssp/v1/ai-bridge/status
```
Auth: X-SSP-Bridge-Token header
Query: ?task_id=br_xxxxx
Response (completed): { status, raw, parsed, received_at }
  → parsed = $this->parse_ai_json(response_text) — 5-stage parser
Response (in progress): { status: "pending"|"sent" }
Response (expired): { status: "expired" }
```

### AJAX Handlers (4)

#### ssp_bridge_setup → Returns install URLs
```json
{
  "token": "ssp_bridge_...",
  "site_url": "https://example.com",
  "script_url": "https://example.com/ai-bridge.user.js?token=...&site=...",
  "direct_url": "https://example.com/wp-content/plugins/.../ai-bridge-serve.php?token=...&site=...",
  "poll_url": "/wp-json/ssp/v1/ai-bridge/pending",
  "response_url": "/wp-json/ssp/v1/ai-bridge/response",
  "status_url": "/wp-json/ssp/v1/ai-bridge/status"
}
```

#### ssp_bridge_create_task → Creates task + returns task_id
```
POST params: prompt (required, max 10000), context_type, force
Returns: { task_id, chatbot }  // chatbot = 'deepseek'|'chatgpt' from usermeta
Blocks if pending task exists and <120s old (unless force=1)
```

#### ssp_bridge_poll_status → Checks result
```
POST params: task_id
Returns: { status, raw, parsed }  // on completed
Returns: { status: "pending"|"sent"|"expired" }
```

#### ssp_bridge_cancel_task → Deletes task
```
POST params: task_id
Deletes: task transient + result transient + active index entry
```

### Userscript (ai-bridge.user.js)

#### Platform Detection
```javascript
// DeepSeek: chat.deepseek.com
// ChatGPT: chatgpt.com, chat.openai.com
// Returns 'deepseek' | 'chatgpt' | null (halts if null)
```

#### DOM Selectors

**DeepSeek Input**:
```javascript
document.querySelector('form textarea') ||
document.querySelector('textarea[placeholder*="Message"]') ||
document.querySelector('textarea')
```

**DeepSeek Response** (6 selectors, priority order):
```javascript
'.ds-markdown', '.markdown-body',
'[data-message-author-role="assistant"] .ds-markdown',
'[data-message-author-role="assistant"] .markdown-body',
'[class*="markdown"]', '[data-message-author-role="assistant"]'
// Uses innerText, minimum 10 chars, returns LAST matching element
```

**DeepSeek Streaming Detection** (7 checks):
```javascript
button[type="submit"] disabled → true
button[aria-label*="Stop"] → true
div[class*="loading"] → true
span[class*="cursor"] → true
div[class*="generating"] → true
div[class*="typing"] → true
Any button text contains "stop" or "توقف" → true
```

**ChatGPT Input**:
```javascript
document.querySelector('div.ProseMirror[contenteditable="true"]') ||
document.querySelector('#prompt-textarea') ||
document.querySelector('div[contenteditable="true"][role="textbox"]')
```

**ChatGPT Response** (3 selectors):
```javascript
'[data-message-author-role="assistant"] .markdown',
'[data-message-author-role="assistant"] .whitespace-pre-wrap',
'[data-message-author-role="assistant"]'
```

**ChatGPT Streaming Detection** (2 checks):
```javascript
button[data-testid="send-button"] disabled → true
button[data-testid="stop-button"] exists → true
```

#### Input Injection
```javascript
// For <textarea> (DeepSeek): uses native HTMLTextAreaElement.prototype.value setter
// Bypasses React synthetic event system
// Fires input + change events

// For contenteditable (ChatGPT): creates textNode, appends to element
// Fires input + change events
// Positions cursor at end via Range/Selection API
```

#### Stability Detection Algorithm
```javascript
// Polls every 1 second
// Response must be >100 chars to start tracking
// Compares current length to lastResponseLength with ±10 char tolerance
// stableCount++ on each stable check
// When stableCount >= 8: response stable for 8 consecutive seconds
// Then waits 1.5s more, fetches final response
// Timeout: 300 seconds (5 minutes) — resolves with last captured text or 'TIMEOUT'
```

#### Response Submission
```javascript
// Converts literal \n to actual newlines (innerText artifact)
// POST to /wp-json/ssp/v1/ai-bridge/response
// Body: { task_id, response_text, status }
// Header: X-SSP-Bridge-Token: ssp_bridge_xxx
// Uses fetch() (NOT GM_xmlhttpRequest)
```

#### Status Widget
```javascript
// Fixed bottom-left, z-index 99999
// Green dot (#22c55e) = Active
// Amber dot (#f59e0b) = Processing
// Red dot (#ef4444) = Error
```

### CORS Strategy
```
1. ssp-cors.php (loaded early in main.php) handles OPTIONS preflight for /wp-json/ssp/*
2. rest_api_init handler serves as backup for GET/POST requests
3. Userscript uses fetch() (not GM_xmlhttpRequest)
4. Access-Control-Allow-Origin: * on all bridge endpoints
```

### Tool-Specific Generators

Each tool builds a prompt with CRITICAL RULES demanding raw JSON-only response:

#### Content Generator (`cgGenerateViaBrowser`)
```
Context: contentgen
Expected JSON: {"title":"","content":"","excerpt":"","tags":[""],"meta_title":"","meta_description":""}
Prompt includes: SEO requirements, E-E-A-T principles, Persian content rules
Form fields filled: pg_result_title, pg_result_content, pg_result_preview, pg_result_hashtags
```

#### Product Generator (`pgGenerateViaBrowser`)
```
Context: productgen
Expected JSON: {"name":"","short_description":"","description":"","regular_price":"","sku":"","categories":[""],"tags":[""]}
Prompt includes: e-commerce copywriting, Persian market pricing
Form fields filled: product name, descriptions, price, SKU, categories, tags
```

#### Post Generator (`pgGeneratePostViaBrowser`)
```
Context: postgen
Expected JSON: {"title":"","message":""}
Prompt includes: social media style, tone/length options
Form fields filled: pg_result_title, pg_result_content, pg_result_hashtags
Direct render: fills textarea + preview div
```

#### SEO Analysis (`seoAnalyzeViaBrowser`)
```
Context: seo
Expected JSON: {"score":0,"summary":"","strengths":[""],"weaknesses":[""],"improvements":[""],"optimized_title":"","optimized_content":""}
Prompt includes: 10 prompt modes (human/redteam/x10think/etc.)
Form fields filled: score display, summary, lists, optimized content
```

#### Brainstorm (`bsGenerateViaBrowser`)
```
Context: brainstorm
Expected JSON: [{"title":"","description":"","type":"post|article","audience":"","angle":"","keywords":""}]
Prompt includes: topic, count, type filter (post/article/mix)
Direct render: bsRenderIdeas() or raw fallback display
```

#### Batch Generation (`batchGenerate`)
```
Context: batchgen
Expected JSON: Array of {title, message, hashtags} objects
Prompt includes: count, style, tone, length, topic
Direct render: renderBatchResults() with send/save/draft actions
```

### Response Parsing Pipeline
```
Server-side: parse_ai_json() — 5-stage parser
  1. Direct json_decode()
  2. Extract JSON array [{...}] via regex
  3. Extract JSON object {...} via regex
  4. Extract from code blocks ```json...```
  5. Aggressive clean: fix newlines, remove trailing commas, retry

Client-side fallback (in pollBridgeStatus):
  1. Direct JSON.parse(raw)
  2. Extract array [{...}]
  3. Extract object {...}
  4. validateAndParseBridgeResponse() — 5 strategies
```

### State Machine (7 States)
```javascript
var bridgeStates = {
    'opening':   { step: 1, status: 'مرورگر چت‌بات باز شد...', progress: 33 },
    'waiting':   { step: 2, status: 'به سایت چت‌بات بروید و منتظر پاسخ بمانید...', progress: 50 },
    'receiving': { step: 3, status: 'پاسخ دریافت شد! در حال پردازش...', progress: 85 },
    'tab_closed':{ step: 2, status: 'تب چت‌بات بسته شد! کار ناتمام ماند.', progress: 50 },
    'timeout':   { step: 2, status: 'پاسخی دریافت نشد. مجدداً تلاش کنید.', progress: 50 },
    'error':     { step: 0, status: 'خطایی رخ داد. لطفاً مجدداً تلاش کنید.', progress: 0 },
    'success':   { step: 3, status: 'پاسخ با موفقیت دریافت شد!', progress: 100 }
};
```

### Retry/Restart Pattern
```javascript
// retryBridgeTask: Cancel old task → create new with same prompt → reopen chatbot tab
// restartBridgeTask: Close modal → re-run original tool function
// fnMap: { contentgen, productgen, postgen, brainstorm, seo, batchgen }
```

### Known Fixes (2026-07-23)
1. `stableCount` reset killed stability detection (DeepSeek `isStreaming()` never returns false)
2. CORS preflight not handled early enough → `ssp-cors.php` loaded in main.php
3. `innerText` newlines break JSON → convert in `sendResponseToPlugin`
4. `parse_ai_json` couldn't handle newlines → character-level scanner
5. `batchgen` case missing from bridge-response listener and restartBridgeTask

---

## 24. BATCH CONTENT GENERATION

- **Styles**: 14 (general, casual, motivational, humorous, formal, authoritative, technical, educational, promotional, news, review, comparison, list, question)
- **Tones**: 8 (natural, friendly, calm, enthusiastic, persuasive, emotional, authoritative, analytical)
- **Length**: 8 options (200-4000 chars)
- **Count**: 1-10 items
- **Citation cleanup**: `cleanCitations()` removes DeepSeek markers (`-N`)

---

## 25. UNIFIED JALALI CALENDAR

`openJalaliPicker(config)` — unified picker with `containerId`, `inputId`, `onConfirm` params.
Legacy wrappers: `manualOpenJalaliPicker`, `schOpenJalaliPicker`, `schBatchOpenJalaliPicker`, `batchOpenPicker`. `pgOpenJalaliPicker` kept separate (dropdown UI).

---

## 26. CROSS-TOOL DEPENDENCY MAP

### `ajax_require_auth()` — 62 calls in 10 files
```
class-ssp-ajax-product.php (31) | ajax-automation.php (7) | ajax-rss.php (4)
ajax-messengers.php (4) | ajax-drafts.php (4) | ai-browser.php (4)
ajax-wp-sites.php (3) | ajax-templates.php (3) | ajax-general.php (2) | seo.php (1)
```

### `get_user_plan()` — 28 calls in 9 files (⚠️ sends email on expiry!)
```
ajax-product.php (22) | ajax-general.php (5) | queue.php (3)
ajax-automation.php (2) | seo.php (2) | ajax-messengers.php (1)
ajax-wp-sites.php (1) | ai-browser.php (1) | portal.php (1)
```

### `call_ai_api()` — 12 calls in 4 files
```
ajax-product.php (5) | ajax-general.php (3) | seo.php (2) | queue.php (2)
```

### `add_to_queue()` — 10 calls in 5 files
```
ajax-product.php (2) | ajax-general.php (2) | queue.php (3)
ajax-rss.php (2) | ajax-automation.php (1)
```

### `parse_ai_json()` — 11 calls in 4 files
```
ajax-product.php (5) | seo.php (2) | ai-browser.php (2) | queue.php (2)
```

### `clean_messenger_text()` — 6 calls in 2 files (⚠️ in queue.php, NOT helpers.php!)
```
ajax-product.php (2) | queue.php (4)
```

### `get_proxy_args()` — 24 calls in 6 files
```
ajax-product.php (6) | wp-publish.php (8) | ajax-general.php (4)
messenger.php (4) | bot-builder.php (1) | seo.php (1)
```

### `get_user_items()` / `set_user_items()` — 31 calls in 7 files
```
get: helpers.php (3) | ajax-automation.php (5) | portal.php (2) | queue.php (1) | ajax-rss.php (1)
set: ajax-messengers.php (3) | ajax-wp-sites.php (3) | ajax-rss.php (5) | ajax-automation.php (7) | queue.php (1)
```

---

## 27. PIPELINE DIAGRAMS

### Message Send
```
INGEST (manual_send | schedule | rss_post)
→ QUEUE: add_to_queue() → daily limit check
→ PROCESS: process_queue() → execute_action()
   IF api mode: process_with_ai() → call_ai_api() → parse_ai_json() → clean_messenger_text()
   format_message() → UTM → get_enabled_messengers()
→ DISPATCH: send_to_messenger() per platform (sleep between)
→ LOG: log_activity() + email on failure
```

### AI Content Generation
```
USER ACTION: "Generate" → handle_generate_ai_content OR Browser Bridge → bridge_create_task
API MODE: call_ai_api() → parse_ai_json() → clean_messenger_text() → response
BROWSER MODE: create_bridge_task() → userscript polls → inject → wait → POST response → poll result → fill form
```

---

## 28. IMPACT ANALYSIS

### When Modifying helpers.php
| Function | Files Affected | Impact |
|----------|---------------|--------|
| `ajax_require_auth()` | 10 files | CRITICAL |
| `get_user_plan()` | 9 files + email | CRITICAL |
| `get/set_user_items()` | 7 files | HIGH |
| `parse_ai_json()` | 4 files | HIGH |
| `get_proxy_args()` | 6 files | MEDIUM |

### When Modifying ai-api.php
| Function | Files Affected | Impact |
|----------|---------------|--------|
| `call_ai_api()` | 4 files | CRITICAL |
| Output format | all callers | MEDIUM |

### When Modifying queue.php
| Function | Files Affected | Impact |
|----------|---------------|--------|
| `add_to_queue()` | 5 files | HIGH |
| `clean_messenger_text()` | 2 files | MEDIUM |
| `process_queue()`/`execute_action()` | entire pipeline | CRITICAL |

---

## 29. HIGH-RISK FILES & GOLDEN RULES

| Rank | File | Reason |
|------|------|--------|
| 1 | class-ssp-helpers.php | 7 utility functions called by 10+ files |
| 2 | class-ssp-queue.php | Main message pipeline |
| 3 | class-ssp-ajax-product.php | 37+ handlers, 7 cross-trait functions |
| 4 | class-ssp-ai-api.php | All AI calls |
| 5 | views/portal.php | 14,138 lines — all UI + JS |
| 6 | class-ssp-ai-browser.php | Bridge protocol |

### Rules
1. helpers.php changed → check all 10+ consuming files
2. AI output format changed → update portal.php (JS) AND queue.php (PHP)
3. New platform → messenger.php + ajax-general.php + portal.php
4. New storage key → helpers.php + AJAX handler + portal.php
5. Never modify prompts without reason → OpenRouter 403 risk
6. Never remove `clean_messenger_text` → HTML leaks to messengers
7. Backup of previous version is in `/backup/` — compare before making changes

---

## 30. PITFALLS

1. **`parse_ai_json()`**: In helpers.php — 5 fallback strategies
2. **`clean_messenger_text()`**: In queue.php, NOT helpers.php
3. **`get_user_plan()`**: Side effects — sends emails on expiry. Don't call in loops
4. **JS data arrays**: Must stay in sync after CRUD (messengerData, wpSiteData, rssFeedData)
5. **AI prompts**: NEVER modify when fixing unrelated bugs. Fix HTML via post-processing
6. **Bridge token**: `ssp_bridge_` + 32 chars, validated via SQL query
7. **Queue order**: Max 5 items/cycle, priority-sorted, 1.5s delay between sends
8. **`ajax_require_auth()`**: Already calls `check_ajax_referer()` — don't call both
9. **Storage keys**: All start with `ssp_` prefix
10. **portal.php**: 14,138 lines — use function names, not line numbers
11. **AI config check**: `$ai_configured` at top of portal.php, guard all AI tabs

---

## 31. UI PATTERNS

### Event Delegation
```javascript
document.querySelector('.ssp-container').addEventListener('click', function(e) {
    var target = e.target.closest('[data-action]');
    if (!target) return;
    switch(target.dataset.action) {
        case 'edit': openEditModal(target.dataset.id); break;
        case 'delete': deleteItem(target.dataset.id); break;
    }
});
```

### AJAX Fetch (with timeout)
```javascript
function sspAjax(action, data) {
    var fd = new FormData();
    fd.append('action', action); fd.append('security', nonce);
    for (var key in data) fd.append(key, data[key]);
    var ctrl = new AbortController();
    var tid = setTimeout(function() { ctrl.abort(); }, 180000);
    return fetch(ajaxurl, { method: 'POST', body: fd, signal: ctrl.signal })
        .then(function(r) { clearTimeout(tid); return r.json(); });
}
```

### SPA Card CRUD
```javascript
insertAdjacentHTML('beforeend', html);  // Add
element.remove();                         // Remove
element.innerHTML = html;                // Update
// Keep JS arrays in sync!
```

### Mobile
```css
@media (max-width: 768px) {
    .ssp-sidebar { position: fixed; right: -300px; width: 280px; transition: right 0.3s; }
    .ssp-sidebar.open { right: 0; }
    .ssp-fab { position: fixed; bottom: 20px; left: 20px; width: 56px; height: 56px; border-radius: 50%; }
}
```

---

## 32. ERROR PATTERNS

### PHP
```php
@set_time_limit(300); @ignore_user_abort(true);  // AI handlers
@ini_set('memory_limit', '256M');                  // Large operations
// JSON fix: str_replace newlines + remove trailing commas
// WP error: is_wp_error($response) → get_error_message()
```

### JS
```javascript
// Fetch timeout: AbortError → showToast('Request timed out', 'error')
// JSON fallback: try JSON.parse → match /\{[\s\S]*\}/ → retry
// Element check: getElementById → if (!el) return
```

---

## 33. FUNCTION SIGNATURES

### helpers.php (40 methods)
```php
// Authentication
private function ajax_require_auth()                    // → user_id
private function ajax_require_admin()                   // → user_id

// Storage
private function get_user_items($user_id, $key)         // → array
private function set_user_items($user_id, $key, $items)
private function next_id($items)                        // → int
private function get_global_items($key)                 // → array
private function set_global_items($key, $items)

// Domain Getters
private function get_user_messengers($user_id)
private function get_user_wp_sites($user_id)
private function get_user_rss_feeds($user_id)
private function get_profile_messengers($user_id, $profile_id = null)
private function get_profile_wp_sites($user_id, $profile_id = null)
private function get_profile_rss_feeds($user_id, $profile_id = null)
private function get_enabled_messengers($user_id)
private function get_today_count($user_id, $logs = null)
private function get_user_drafts($user_id)
private function set_user_drafts($user_id, $drafts)
private function get_user_template_items($user_id)
private function set_user_template_items($user_id, $templates)
private function get_link_settings($user_id)
private function set_link_settings($user_id, $settings)

// Plan
private function get_user_plan($user_id)                // → 'free'|'pro' (⚠️ email side effect)

// Proxy
private function get_proxy_settings()                   // → array (cached)
private function get_proxy_args()                       // → array

// AI
private function parse_ai_json($content)                // → array|null
private function clean_json_string($json)               // → string
private function fix_newlines_in_json_strings($json)    // → string

// SEO (in helpers)
private function analyze_content_seo($title, $content, $hashtags, $platform = 'general')
private function extract_keywords($text)
private function calculate_readability($text, $word_count, $sentence_count)
private function get_platform_seo_checks($platform, $title, $content, $hashtags)
private function generate_meta_description($title, $content)
private function generate_seo_title_suggestions($title, $content, $platform)

// Utility
private function get_extension_from_mime($mime)
private function get_wp_rest_type($post_type)
private function append_utm_params($url, $user_id, $platform = '')
private function log_activity($user_id, $platform, $title, $message, $status, $response, $ai_provider, $ai_tokens)

// Jalali
private function gregorian_to_jalali($gy, $gm, $gd)
private function gregorian_to_jalali_str($date_str, $format = 'Y/m/d H:i')
private function jalali_date_short($date_str)
```

### queue.php (12 methods)
```php
public function auto_generate_content()
private function generate_ai_content($user_id, $topic)
private function clean_messenger_text($text)            // → string
private function get_default_ai_prompt($topic)
public function on_post_publish($new_status, $old_status, $post)
public function add_to_queue($user_id, $action_type, $payload, $priority = 5)
public function process_queue()
private function execute_action($user_id, $action_type, $payload)
private function action_publish_content($user_id, $payload, $action_type)
private function process_with_ai($user_id, $title, $message)  // → array
public function process_schedules()
private function get_next_recurring($current, $type)
```

### ai-api.php (5 methods)
```php
private function call_ai_api($provider, $api_key, $model, $prompt, $json_mode = false)
// → ['content' => string, 'tokens_used' => int, 'cost' => float|null]
private function send_ai_request($provider, $api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server)
private function call_openai_compat($provider, $api_key, $model, $prompt, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server, $endpoint = '')
private function call_anthropic($api_key, $model, $prompt, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server)
private function call_gemini($api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server)
```

### network.php (8 methods)
```php
private function resolve_doh($domain, $doh_server = 'cloudflare')
private function wp_remote_with_doh($url, $args = [], $doh_server = 'cloudflare', $method = 'POST')
private function test_doh_connectivity($doh_server = 'cloudflare')
private function get_telegram_relay_settings()
private function is_telegram_relay_enabled()
private function send_via_relay($telegram_method, $data)
private function test_telegram_relay($test_chat_id = '')
private function test_basic_connectivity()
```

### messenger.php (9 methods)
```php
private function format_message($user_id, $title, $message, $url, $hashtags)
private function send_to_messenger($platform, $token, $message, $channel_id = '', $image_url = '')
private function is_video_url($url)
private function send_whatsapp($token, $message, $channel_id, $image_url, $has_media, $is_video = false)
private function send_instagram($token, $message, $channel_id, $image_url, $has_image)
private function send_rubika($token, $message, $channel_id, $image_url, $has_media, $is_video = false)
private function send_bot_platform($platform, $token, $message, $channel_id, $image_url, $has_media, $is_video = false)
private function download_file_for_upload($url, $proxy_args = [])
private function send_media_group($platform, $token, $channel_id, $media_urls, $caption = '')
```

### wp-publish.php (18 methods)
```php
private function publish_to_wp_site($site, $title, $message, $url, $hashtags, $featured_image_url = '')
private function upload_featured_image($site, $image_url)
private function generate_ai_image($user_id, $prompt, $size = '1024x1024', $quality = 'standard')
private function build_product_payload($data)
private function build_post_payload($data, $content_type)
private function process_product_images($site, $data, $files)
private function upload_image_to_remote($site, $image_url, $resize = '')
private function upload_file_to_remote($site, $file, $resize = '')
private function resize_image_file($source, $target_size, $mime = 'image/jpeg')
private function publish_woo_product($site, $payload)
private function publish_wp_content($site, $payload, $content_type)
private function get_user_product_templates($user_id)
private function set_user_product_templates($user_id, $templates)
private function get_user_publish_history($user_id)
private function add_publish_history($user_id, $entry)
private function get_product_ai_prompt($content_type, $name, $brief, $prompt_mode, $custom_prompt)
private function build_prompt_from_saved_template($template, $product_name, $product_brief = '')
private function get_product_prompt_templates()  // Returns 8 built-in templates
```

### cleanup.php (8 methods)
```php
public function run_cleanup()
private function cleanup_stale_scenario_steps()
private function cleanup_old_logs()
private function cleanup_old_queue()
private function check_webhook_rate_limit($ip)
private function encrypt_token($token)
private function decrypt_token($encrypted_token)
private function sanitize_webhook_input($data)
```

### link-shortener.php (6 methods)
```php
private function shorten_url($url, $settings)
private function create_short_link($long_url)
private function get_short_url($code, $user_id = null)
private function generate_short_code($length = 6)
public function handle_short_link_redirect()
public function get_short_link_stats($user_id = null)
```

### bot-builder.php (55 methods — key ones only)
```php
// CRUD
private function get_user_bot_configs($user_id)
private function set_user_bot_configs($user_id, $configs)
private function get_bot_config($user_id, $bot_id)
private function update_bot_config($user_id, $bot_id, $config)
private function get_bot_config_cached($user_id, $bot_id)
private function clear_bot_config_cache($user_id, $bot_id)
private function check_bot_limits($user_id)

// API
private function bot_api_request($platform, $token, $method, $data = [], $proxy_args = [])
private function bot_api_request_with_retry($platform, $token, $method, $data = [], $max_retries = SSP_BOT_RETRY_MAX_ATTEMPTS)
private function send_bot_message($platform, $token, $chat_id, $text, $config)

// Handlers (21 public methods)
handle_save_bot_config, handle_delete_bot, handle_get_bot_config
handle_add/delete/update_bot_command
handle_add/delete/update_bot_button
handle_add/delete/update_bot_auto_reply
handle_set_bot_welcome, handle_set_bot_menu
handle_test/set_bot_webhook, handle_get_bot_stats, handle_generate_bot_code
handle_debug_bot_list, handle_debug_bot_buttons
handle_load_bot_template, handle_apply_bot_template
handle_save/delete_bot_scenario

// Webhook
public function handle_bot_webhook($request)
private function build_keyboard_markup($buttons, $commands = [])
private function build_button_object($btn, $commands = [])
private function execute_auto_reply($trigger, $config, $user_id, $telegram_user, $platform, $token, $chat_id)
private function build_child_keyboard($child_buttons, $parent_value, $config)
private function find_button_by_value($buttons, $value, $depth = 0)
private function find_parent_button($buttons, $child_value, $depth = 0)
private function execute_scenario_action($action, $config, $user_id, $telegram_user, $chat_id)
private function replace_bot_placeholders($text, $user_id, $bot_config, $telegram_user = [])

// Templates
private function get_bot_templates()  // Returns 3 templates: content_publisher, faq_bot, appointment
private function tpl_btn($text, $value, $row, $col = 0)
private function tpl_reply($trigger, $response)

// Scenarios
private function get_user_scenario_step($user_id, $bot_id)
private function set_user_scenario_step($user_id, $bot_id, $step_id)
private function find_scenario_step($scenarios, $step_id)
private function find_scenario_by_trigger($scenarios, $trigger)
private function build_scenario_keyboard($step)

// Cleanup
public function cleanup_all_bot_buttons_style()
private function clear_all_user_bot_cache($user_id)
private function sync_bot_commands($config)
private function generate_bot_webhook_code($config)
```

### browser.php (15 methods)
```php
private function get_or_create_bridge_token($user_id)
private function validate_bridge_token($token)
private function create_bridge_task($user_id, $prompt, $context = [])
private function cleanup_bridge_task($user_id, $task_id)
public function bridge_get_pending_task($request)
public function bridge_receive_response($request)
public function bridge_get_task_status($request)
public function register_bridge_rewrite()
public function add_bridge_query_var($vars)
public function maybe_flush_bridge_rewrite()
public function handle_bridge_userscript_rewrite()
public function handle_bridge_setup()
public function handle_bridge_create_task()
public function handle_bridge_poll_status()
public function handle_bridge_cancel_task()
```

### content-distribution.php (18 methods)
```php
public function handle_get/save/delete/toggle/preview/run_distribution_now
public function process_distributions()  // multi-profile support (all profiles)
private function should_run_distribution($dist)
private function execute_single_distribution($user_id, $dist)
private function get_distribution_items($user_id, $dist, $skip_history = false)  // $skip_history for preview
private function format_distribution_message($template, $item)
private function get_distribution_history($user_id, $dist_id)
private function clear_distribution_history($user_id, $dist_id)
private function mark_item_distributed($user_id, $dist, $item)
private function get_distribution_sent_count($user_id, $dist_id)
private function update_distribution_last_run($user_id, $dist, $time_str = '')
private function get_users_with_active_distributions()  // excludes empty serialized arrays
private function set_profile_items($user_id, $key, $items, $profile_id = null)
```

### ajax-product.php (36 methods — key ones only)
```php
private function fix_ai_newlines($text)
handle_generate_product_ai, handle_brainstorm_ideas, handle_generate_product
handle_fetch_woo_categories, handle_upload_product_image, handle_upload_post_image
handle_save_product_prompt, handle_save/get/delete_product_template
handle_get/clear_publish_history, handle_bulk_generate_products
handle_fetch_remote_product, handle_fetch_woo_brands, handle_fetch_woo_shipping
handle_batch_generate, handle_send_batch
handle_shorten_url, handle_save/get/delete_link_settings
handle_save_short_url_format, handle_save_custom_domain
handle_save/test_proxy, handle_network_diagnostics
handle_save_doh_settings, handle_test_doh, handle_test_telegram_dns
handle_save_utm_settings, handle_save/test_email_settings
handle_generate_image, handle_save_image_settings
```

### ajax-profiles.php (11 methods)
```php
public function handle_get/add/update/switch/delete_profile
private function get_active_profile_id($user_id)
private function get_profile_items($user_id, $key, $profile_id = null)
private function set_profile_item($user_id, $key, $item, $profile_id = null)
private function delete_profile_item($user_id, $key, $item_id, $profile_id = null)
private function remove_items_by_profile($user_id, $profile_id)  // includes distributions
private function ensure_profile_fields($user_id)  // includes template_items
```

### ajax-wp-sites.php (7 methods)
```php
handle_add/delete/update/test_wp_site      // test supports site_id server-side lookup
handle_fetch_wp_categories
```

---

## 34. PERFORMANCE & TROUBLESHOOTING

### Rate Limiting
- Messenger: 1.5s between sends | WP posts: 3s | AI retry: 5s | Queue: max 5/cycle

### Memory
- AI handlers: `@set_time_limit(300); @ignore_user_abort(true);`
- Large ops: `@ini_set('memory_limit', '256M');`

### Quick Fixes
| Problem | Check |
|---------|-------|
| Cron not working | Admin notice → AJAX ssp_activate_cron |
| Telegram failing | Relay → DoH → proxy → direct chain |
| AI API failing | AJAX ssp_test_ai_live (3-step wizard) |
| Bridge broken | Token valid? Userscript installed? Tab open? |
| Queue stuck | Cron scheduled? Manual: ssp_manual_process |
| Bot webhook | URL correct? Token valid? Rate limit 30/60s |

---

## 35. KNOWN ISSUES

1. `handle_keyword_analyzer()` may reference undefined `$title`
2. Persian typo: "مقاسبه" → should be "مقایسه"
3. `handle_site_audit()` checks `is_wp_error()` but `call_ai_api()` throws exceptions
4. Bot user lookup limited to 100 users — may fail at scale
5. Some ajax-product handlers missing `check_ajax_referer`

---

## 36. HOSTING

- **WP site**: arzanyad_webyar | DB prefix: `wy_`
- **CRITICAL**: Hosting blocks CREATE TABLE
- **wp-cron**: Unreliable on shared hosting

---

## 37. CHANGE LOG

### 2026-07-29
- **Distribution tool overhaul**: Replaced `mode` (new/existing) with `only_new` checkbox. History check now applies to both modes. `skip_history` parameter for preview bypasses history AND delay filters. Fixed `target_messengers` empty bug (`array_filter` on ID list). Fixed `all_distributed` reset to re-apply delay filter. Added `filter_tags`, `filter_min_price`, `filter_max_price` UI fields. Default template cleaned (`# ` and `\\n` removed). Fixed hardcoded colors in distribution UI (use CSS vars). File: `class-ssp-content-distribution.php` (307→454 lines).
- **WP Sites test connection fix**: `handle_test_wp_site` now supports `site_id` lookup — reads credentials server-side instead of relying on JS `wpSiteData.app_password`. Edit form no longer clears password (only updates if new value provided). File: `class-ssp-ajax-wp-sites.php` (82→103 lines). Portal: placeholder "خالی بگذارید تا تغییر نکند".
- **Cascading delete**: Added `'distributions'` to `remove_items_by_profile()` in `class-ssp-ajax-profiles.php`.
- **Mobile drawer fix**: Added `botbuilder` and `distribution` to drawer, removed duplicate `messengers` from منابع و اتصالات.
- **Profile colors**: Added `border: 1px solid var(--border)` to color dots in profile modal for light theme visibility. Changed "فعال" badge from `info` to `success`.
- **Mobile header**: Sticky header on mobile (`position: sticky; top: 0; z-index: 100`). Consistent sizing for controls (profile switcher 32px, gear button 32x32, theme toggle 36x36). Removed `border-radius: 0`.
- **Profile switcher**: Removed inline SVG background-image, added clean CSS class `ssp-profile-switcher` with `appearance: none`, native select dropdown arrow restored.

### 2026-07-25
- **RSS Feeds complete redesign**: Content cleaning (HTML/URLs/ads removal), 4 content modes (summary/title_only/title_link/full), configurable max length, destination control (all/specific messengers). File: `class-ssp-ajax-rss.php` (131→263 lines). New methods: `clean_rss_content()`, `build_rss_message()`, `parse_rss_targets()`.
- **RSS preview-first pattern**: Fetch Now shows preview modal instead of auto-sending. User selects items, edits content, then sends/drafts/schedules. Files: portal.php (preview modal + JS functions), class-ssp-ajax-rss.php (handle_fetch_rss_now rewritten).
- **RSS → Drafts integration**: RSS items saved as drafts with `draft_type: 'rss'`. AJAX: `ssp_rss_save_drafts`.
- **RSS → Calendar integration**: RSS items scheduled with configurable interval (15/30/60/120 min). AJAX: `ssp_rss_schedule`. Pro-only.
- **Triggers merged into bot builder**: Removed triggers tab from sidebar + content section + mobile drawer + all orphaned JS functions. Auto-reply section in bot builder serves identical purpose.
- **Enterprise audit fixes**: Bot webhook N+1 → direct DB query, WP Object Cache for global items, bulk user meta loading, memory guards on AI handlers, centralized settings cache.
- **Security audit**: Score 68/100. SSRF IPv6 gap, WP_Error disclosure, XSS in portal.php documented.
- **Enhanced cleanup**: 4 new operations — bridge transients, orphaned distribution history, rate limit transients, scenario steps N+1 fix.
- **3 new AJAX handlers registered** in `class-ssp-core.php`: `ssp_rss_send_selected`, `ssp_rss_save_drafts`, `ssp_rss_schedule`.

### 2026-07-23
- Browser Bridge: Fixed 7 root causes (stableCount, CORS, innerText, parse_ai_json, batchgen)
- Batch content generation tool (14 styles, 8 tones)
- Unified Jalali calendar picker

### 2026-07-22
- Content Generation Hub redesign, AI image removed, draft loader
- Rubika image/video upload fix (raw curl_exec), timezone display fix
- Platform icons, file upload, video support, album support
- Quick Send + Bulk Send merged into unified Send tab
- Content Spinning removed
- Rubika/Eitaa image sending fix, PHP 8.1 sleep deprecation fix

### 2026-07-21
- Telegram relay bot support
- Profile System (business workspaces)
- Smart Prompt Builder (10-field templates)
- Auto Content Distribution (3 modes)
- DNS over HTTPS (DoH) for Telegram
- License activation AJAX fix, email settings fix
- AI configuration check on all AI tabs
- Admin license management enhancements
- Messenger token display fix, Rubika API integration fix

---

## 38. VERSION HISTORY

- **v15.0.0** (current): Profiles, Prompt Builder, Distribution, Bot Builder (12 types), SEO (16 tools), Browser Bridge, Batch Gen
- **v14.0.0**: Bot Builder, E-E-A-T, Site Audit
- **v13.0.0**: Browser Bridge, AI Prompt Modes (10)
