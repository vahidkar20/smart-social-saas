const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

// Use regex to add type="button" to <button> tags that don't have a type attribute and aren't submit buttons
php = php.replace(/<button([^>]*class="ssp-btn-(primary|secondary)"[^>]*)>/g, (match, p1) => {
    if (!p1.includes('type=')) {
        return `<button type="button"${p1}>`;
    }
    return match;
});

// Also check .ssp-modal-close
php = php.replace(/<button([^>]*class="ssp-modal-close"[^>]*)>/g, (match, p1) => {
    if (!p1.includes('type=')) {
        return `<button type="button"${p1}>`;
    }
    return match;
});

fs.writeFileSync('includes/views/portal.php', php);
console.log('patched modal buttons');
