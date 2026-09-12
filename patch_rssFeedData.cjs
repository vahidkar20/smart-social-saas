const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

let target = `$rss_feeds_json = json_encode(array_map(function($f) {
    return ['id' => (int)$f['id'], 'feed_name' => $f['feed_name'], 'feed_url' => $f['feed_url'],
            'is_active' => (int)$f['is_active'], 'auto_fetch' => (int)$f['auto_fetch']];
}, $rss_feeds));`;

let replacement = `$rss_feeds_json = json_encode(array_map(function($f) {
    return ['id' => (int)$f['id'], 'feed_name' => $f['feed_name'], 'feed_url' => $f['feed_url'],
            'is_active' => (int)$f['is_active'], 'auto_fetch' => (int)$f['auto_fetch'],
            'clean_ads' => (int)($f['clean_ads'] ?? 1), 'clean_urls' => (int)($f['clean_urls'] ?? 1),
            'extract_content' => (int)($f['extract_content'] ?? 0), 'max_length' => (int)($f['max_length'] ?? 500),
            'content_mode' => $f['content_mode'] ?? 'summary'];
}, $rss_feeds));`;

php = php.replace(target, replacement);
fs.writeFileSync('includes/views/portal.php', php);
console.log('patched rssFeedData array');
