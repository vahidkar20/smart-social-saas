const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-rss.php', 'utf8');

const oldCode = `        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $mode = sanitize_text_field($_POST['ai_mode'] ?? 'summarize');
        $ai_mode = get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api';

        if (empty($text)) wp_send_json_error(['message' => 'متنی برای پردازش وجود ندارد']);

        $prompt = $this->build_rss_ai_prompt($text, $mode);`;

const newCode = `        $items_json = stripslashes($_POST['items'] ?? '');
        $items = json_decode($items_json, true);
        $mode = sanitize_text_field($_POST['ai_mode'] ?? 'summarize');
        $ai_mode = get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api';
        
        $text = '';
        if (is_array($items)) {
            foreach ($items as $item) {
                $text .= "عنوان: " . ($item['title'] ?? '') . "\\n";
                $text .= "محتوا: " . ($item['content'] ?? '') . "\\n\\n";
            }
        } else {
            $text = sanitize_textarea_field($_POST['text'] ?? '');
        }

        if (empty(trim($text))) wp_send_json_error(['message' => 'متنی برای پردازش وجود ندارد']);

        $prompt = $this->build_rss_ai_prompt($text, $mode);`;

if (php.includes(oldCode)) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/class-ssp-ajax-rss.php', php);
    console.log('Patched handle_rss_ai_process successfully.');
} else {
    console.log('Could not find code to patch.');
}
