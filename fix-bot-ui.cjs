const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let botUi = `
window.showAddBotForm = function() {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) {
        editor.style.display = 'block';
        let form = document.getElementById('bot_config_form');
        if(form) form.reset();
    }
};

window.editBot = function(id) {
    // Ideally we would fetch bot data by ID and populate the form
    showToast('در حال بارگذاری اطلاعات بات...', 'info');
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) editor.style.display = 'block';
};

window.showBotList = function() {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(editor) editor.style.display = 'none';
    if(list) list.style.display = 'block';
};

window.toggleTemplatesSection = function() {
    let tpl = document.getElementById('bot_templates_section');
    if(tpl) tpl.style.display = tpl.style.display === 'none' ? 'block' : 'none';
};

// Simple toggles for inline forms
function toggleInlineForm(showId, hideIds) {
    if(showId) {
        let el = document.getElementById(showId);
        if(el) el.style.display = 'block';
    }
    if(hideIds && hideIds.length) {
        hideIds.forEach(id => {
            let el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });
    }
}
window.showAddCommandForm = () => toggleInlineForm('bot_command_form', []);
window.hideAddCommandForm = () => toggleInlineForm('', ['bot_command_form']);
window.showAddButtonForm = () => toggleInlineForm('bot_button_form', []);
window.hideAddButtonForm = () => toggleInlineForm('', ['bot_button_form']);
window.showAddAutoReplyForm = () => toggleInlineForm('bot_autoreply_form', []);
window.hideAddAutoReplyForm = () => toggleInlineForm('', ['bot_autoreply_form']);
window.showAddScenarioForm = () => toggleInlineForm('bot_scenario_form', []);
window.hideAddScenarioForm = () => toggleInlineForm('', ['bot_scenario_form']);
`;

core = core.replace("// Create a generic fallback", botUi + "\n// Create a generic fallback");

['showAddBotForm', 'editBot', 'showBotList', 'toggleTemplatesSection', 'showAddCommandForm', 'hideAddCommandForm', 'showAddButtonForm', 'hideAddButtonForm', 'showAddAutoReplyForm', 'hideAddAutoReplyForm', 'showAddScenarioForm', 'hideAddScenarioForm'].forEach(fn => {
    core = core.replace(`'${fn}', `, "");
});

fs.writeFileSync('assets/js/portal-core.js', core);
