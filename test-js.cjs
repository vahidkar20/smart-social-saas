const fs = require('fs');
let html = fs.readFileSync('includes/views/portal.php', 'utf8');
let scripts = html.match(/<script>(.*?)<\/script>/gs);
if (scripts) {
    scripts.forEach((script, i) => {
        let code = script.replace(/<\/?script>/g, '');
        // Replace simple PHP tags with empty strings to make it parseable
        code = code.replace(/<\?php[\s\S]*?\?>/g, '""');
        try {
            new Function(code);
            console.log(`Script ${i} is valid.`);
        } catch (e) {
            console.log(`Script ${i} Error:`, e.message);
        }
    });
}
