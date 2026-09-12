const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

let target = `'content_mode' => $f['content_mode'] ?? 'summary'];`;
let replacement = `'content_mode' => $f['content_mode'] ?? 'summary',
            'message_template' => $f['message_template'] ?? ''];`;

php = php.replace(target, replacement);
fs.writeFileSync('includes/views/portal.php', php);
console.log('patched rssFeedData array 2');
