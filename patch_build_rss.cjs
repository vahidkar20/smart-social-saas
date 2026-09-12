const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-rss.php', 'utf8');

let target = `    private function build_rss_message($title, $content, $url, $feed) {
        $mode = $feed['content_mode'] ?? 'summary';

        switch ($mode) {
            case 'title_only':
                return $title;

            case 'title_link':
                return $title . "\\n\\n" . $url;

            case 'full':
                return $content;

            case 'summary':
            default:
                $message = '';
                if (!empty($title)) $message .= $title . "\\n\\n";
                if (!empty($content)) $message .= $content;
                if (!empty($url)) $message .= "\\n\\n🔗 " . $url;
                return trim($message);
        }
    }`;

let replacement = `    private function build_rss_message($title, $content, $url, $feed) {
        if (!empty($feed['message_template'])) {
            $excerpt = wp_trim_words($content, 30, '...');
            $tpl = $feed['message_template'];
            $tpl = str_replace('{title}', $title, $tpl);
            $tpl = str_replace('{content}', $content, $tpl);
            $tpl = str_replace('{url}', $url, $tpl);
            $tpl = str_replace('{excerpt}', $excerpt, $tpl);
            return trim($tpl);
        }
        
        $mode = $feed['content_mode'] ?? 'summary';

        switch ($mode) {
            case 'title_only':
                return $title;

            case 'title_link':
                return $title . "\\n\\n" . $url;

            case 'full':
                return $content;

            case 'summary':
            default:
                $message = '';
                if (!empty($title)) $message .= $title . "\\n\\n";
                if (!empty($content)) $message .= $content;
                if (!empty($url)) $message .= "\\n\\n🔗 " . $url;
                return trim($message);
        }
    }`;

php = php.replace(target, replacement);
fs.writeFileSync('includes/class-ssp-ajax-rss.php', php);
console.log('patched build_rss_message');
