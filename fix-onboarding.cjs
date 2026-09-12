const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

core = core.replace(
    /window\.skipOnboarding = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.skipOnboarding = function() { window.closeModal('modal_onboarding'); };"
);

core = core.replace(
    /window\.skipOnboardingStep = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    `window.skipOnboardingStep = function(step, skip) {
    if (skip) {
        let els = document.querySelectorAll('.ssp-onboarding-step');
        els.forEach(e => e.style.display = 'none');
        if (step === 1) document.getElementById('onboarding_step_2').style.display = 'block';
        if (step === 2) document.getElementById('onboarding_step_3').style.display = 'block';
    } else {
        window.closeModal('modal_onboarding');
        if (step === 1) window.switchTab('messengers', document.querySelector('[data-tab=messengers]'));
        if (step === 2) window.switchTab('ai', document.querySelector('[data-tab=ai]'));
        if (step === 3) window.switchTab('manual', document.querySelector('[data-tab=manual]'));
    }
};`
);

fs.writeFileSync('assets/js/portal-core.js', core);
