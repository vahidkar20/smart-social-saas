const fs = require('fs');
let portal = fs.readFileSync('includes/views/portal.php', 'utf8');

// I will just append a script tag loading our core JS at the very end of portal.php
if (!portal.includes('portal-core.js')) {
    portal += `\n<script src="<?php echo plugins_url('assets/js/portal-core.js', dirname(__DIR__, 2) . '/main.php'); ?>?v=<?php echo SSP_VERSION; ?>"></script>\n`;
}
if (!portal.includes('portal-ai.js')) {
    portal += `<script src="<?php echo plugins_url('assets/js/portal-ai.js', dirname(__DIR__, 2) . '/main.php'); ?>?v=<?php echo SSP_VERSION; ?>"></script>\n`;
}

fs.writeFileSync('includes/views/portal.php', portal);
