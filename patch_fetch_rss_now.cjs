const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-rss.php', 'utf8');

let target = `                    $title = (string)($item->title ?? '');
                    $raw_content = (string)($item->description ?? $item->content ?? '');
                    $clean_content = $this->clean_rss_content($raw_content, $feed);
                    $url = (string)($item->link ?? '');
                    $message = $this->build_rss_message($title, $clean_content, $url, $feed);
                    $pub_date = (string)($item->pubDate ?? '');
                    $is_new = !in_array($guid, $last_items);
                    $extracted = '';
                    if ($extract_now && !empty($url) && $extract_count < 3) {
                        $extracted = $this->extract_article_content($url);
                        if (!empty($extracted)) $extract_count++;
                    }`;

let replacement = `                    $title = (string)($item->title ?? '');
                    $raw_content = (string)($item->description ?? $item->content ?? '');
                    $url = (string)($item->link ?? '');
                    
                    $extracted = '';
                    if ($extract_now && !empty($url) && $extract_count < 3) {
                        $extracted = $this->extract_article_content($url);
                        if (!empty($extracted)) $extract_count++;
                    }
                    
                    $source_content = !empty($extracted) ? $extracted : $raw_content;
                    $clean_content = $this->clean_rss_content($source_content, $feed);
                    $message = $this->build_rss_message($title, $clean_content, $url, $feed);
                    
                    $pub_date = (string)($item->pubDate ?? '');
                    $is_new = !in_array($guid, $last_items);`;

php = php.replace(target, replacement);
fs.writeFileSync('includes/class-ssp-ajax-rss.php', php);
console.log('patched handle_fetch_rss_now ordering');
