const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-rss.php', 'utf8');

let target = `$id = intval($_POST['feed_id']);`;
let replacement = `$id = intval($_POST['feed_id'] ?? $_POST['id'] ?? 0);`;

php = php.replace(target, replacement);

let updateTarget = `$id = intval($_POST['feed_id']);`;
php = php.replace(updateTarget, replacement);

fs.writeFileSync('includes/class-ssp-ajax-rss.php', php);
console.log('patched handle_delete_rss_feed');
