const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');
let extraJs = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

portal = portal.replace('})();\n        </script>', '})();\n' + extraJs + '\n        </script>');
fs.writeFileSync('includes/views/portal.php', portal);
