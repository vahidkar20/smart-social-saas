const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

php = php.replace(/<button([^>]*class="ssp-btn-(danger|test|success)"[^>]*)>/g, (match, p1) => {
    if (!p1.includes('type=')) {
        return `<button type="button"${p1}>`;
    }
    return match;
});

fs.writeFileSync('includes/views/portal.php', php);
console.log('patched more buttons');
