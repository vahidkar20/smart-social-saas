const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-rss.php', 'utf8');

// handle_add_rss_feed
let targetAdd = `'content_mode' => sanitize_text_field($_POST['content_mode'] ?? 'summary'),`;
let replacementAdd = `'content_mode' => sanitize_text_field($_POST['content_mode'] ?? 'summary'),
            'message_template' => sanitize_textarea_field($_POST['message_template'] ?? ''),`;
php = php.replace(targetAdd, replacementAdd);

// handle_update_rss_feed
let targetUpdate = `$f['content_mode'] = sanitize_text_field($_POST['content_mode'] ?? 'summary');`;
let replacementUpdate = `$f['content_mode'] = sanitize_text_field($_POST['content_mode'] ?? 'summary');
                if (isset($_POST['message_template'])) $f['message_template'] = sanitize_textarea_field($_POST['message_template']);`;
php = php.replace(targetUpdate, replacementUpdate);

fs.writeFileSync('includes/class-ssp-ajax-rss.php', php);
console.log('patched php for message_template save');
