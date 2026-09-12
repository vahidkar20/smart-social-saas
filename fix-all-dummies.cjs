const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let fns = [
    'addScenarioStep', 'closeDistributionForm', 'previewDistribution', 'seoResetPromptMode', 
    'seoAnalyzeViaBrowser', 'analyzeContentGap', 'analyzeTopicCluster', 'analyzeFeaturedSnippet', 
    'analyzeVoiceSearch', 'generateSeoChecklist', 'importToKeywordAnalyzer', 'analyzeKeywords', 
    'importToSerpPreview', 'auditUrl', 'importToGeo', 'analyzeGeo', 'importToEeat', 'analyzeEeat', 
    'generateSchema', 'siteAuditResetPromptMode', 'runSiteAudit', 'runSiteAuditViaBrowser', 
    'testShortenUrl', 'copyShortLink', 'deleteShortLink', 'exportCSV', 'toggleLogDetail', 
    'rssSendSelected', 'rssSaveDrafts', 'rssScheduleItems', 'rssAiProcessSelected', 
    'skipOnboarding', 'skipOnboardingStep', 'savePromptTemplate', 'previewPromptTemplate', 
    'closeTemplateModal', 'saveTemplateFromModal'
];

let newCode = '\n// === STUBS FOR INCOMPLETE FEATURES ===\n';
fns.forEach(fn => {
    newCode += `window.${fn} = function() { showToast('این ویژگی در حال توسعه است', 'info'); };\n`;
});

core = core + newCode;
fs.writeFileSync('assets/js/portal-core.js', core);
