const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// Replace ssp_delete_schedule usage with ssp_cancel_schedule with permanent=1 for consistency in JS where needed, or leave it since it works identically. 
// We saw both handle_delete_schedule and handle_cancel_schedule do the right things.

// We should fix loadCalendar fallback if fetchFromServer fails, or just ensure ajaxurl is correct.
// In portal-core.js: loadCalendar function.
code = code.replace(/fetch\(window\.ajaxurl, \{ method: 'POST', body: fd \}\)/g, "fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })");
code = code.replace(/if \(fetchFromServer && window\.ajaxurl\)/g, "if (fetchFromServer && (window.ajaxurl || '/wp-admin/admin-ajax.php'))");

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed loadCalendar fallback');
