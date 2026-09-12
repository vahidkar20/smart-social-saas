const fs = require('fs');
let php = fs.readFileSync('includes/class-ssp-ajax-wp-sites.php', 'utf8');

let target = `$site_id = intval($_POST['site_id'] ?? 0);`;
let replacement = `$site_id = intval($_POST['site_id'] ?? $_POST['id'] ?? 0);`;

php = php.replace(target, replacement);
fs.writeFileSync('includes/class-ssp-ajax-wp-sites.php', php);
console.log('patched handle_test_wp_site');
