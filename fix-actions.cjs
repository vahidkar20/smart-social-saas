const fs = require('fs');

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');
core = core.replace(/'ssp_clear_queue'/g, "'ssp_cancel_all_queue'");
core = core.replace(/'ssp_add_wpsite'/g, "'ssp_add_wp_site'");
core = core.replace(/'ssp_update_wpsite'/g, "'ssp_update_wp_site'");
fs.writeFileSync('assets/js/portal-core.js', core);

let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');
ai = ai.replace(/'ssp_generate_ideas'/g, "'ssp_brainstorm_ideas'");
ai = ai.replace(/'ssp_generate_article_ai'/g, "'ssp_generate_ai_content'");
fs.writeFileSync('assets/js/portal-ai.js', ai);

