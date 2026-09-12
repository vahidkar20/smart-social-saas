const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');
let extra = fs.readFileSync('scripts-extra.js', 'utf8');

// I'll replace the existing extra with the new one
portal = portal.replace(/\/\/ ===== EXTENDED JAVASCRIPT FOR DASHBOARD =====[\s\S]*<\/script>/g, extra + '\n        </script>');

fs.writeFileSync('includes/views/portal.php', portal);
