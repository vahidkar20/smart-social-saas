const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

[
    'pgPublish', 'pgPreview', 'cgPublish', 'cgPreview', 'pgSaveAsDraftPost', 
    'cgGenerateWithAI', 'pgGeneratePostWithAI', 'bsGenerate', 'pgToggleView', 'switchPgMediaTab', 'testAiConnection'
].forEach(fn => {
    core = core.replace("'" + fn + "', ", "");
});

fs.writeFileSync('assets/js/portal-core.js', core);
