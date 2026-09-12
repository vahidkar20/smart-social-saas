const fs = require('fs');
let content = fs.readFileSync('includes/views/portal.php', 'utf8');

const replacements = [
    { match: /return div\.innerHTML;\s+window\.escapeHtml = escapeHtml;/g, replace: "return div.innerHTML;\n            }\n            window.escapeHtml = escapeHtml;" },
    { match: /class="ssp-connection-status"><\/span><\/div>';\s+function createWpSiteCard\(s\) \{/g, replace: "class=\"ssp-connection-status\"></span></div>';\n            }\n            function createWpSiteCard(s) {" },
    { match: /class="ssp-connection-status"><\/span><\/div>';\s+function createRssFeedCard\(f\) \{/g, replace: "class=\"ssp-connection-status\"></span></div>';\n            }\n            function createRssFeedCard(f) {" },
    { match: /<\/div><\/div>';\s+function createScheduleCard\(s\) \{/g, replace: "</div></div>';\n            }\n            function createScheduleCard(s) {" },
    { match: /<\/div><\/div><\/div>';\s+\/\/\s+=====\s+SPA Helpers\s+=====/g, replace: "</div></div></div>';\n            }\n            // ===== SPA Helpers =====" },
    { match: /if \(el\) el\.remove\(\);\s+function showEmptyState/g, replace: "if (el) el.remove();\n            }\n            function showEmptyState" },
    { match: /actionBtnHtml\+'<\/div>' : ''\) \+ '<\/div>';\s+\}\s+function showSaved/g, replace: "actionBtnHtml+'</div>' : '') + '</div>';\n                }\n            }\n            function showSaved" },
    { match: /el\.classList\.remove\('show'\); \}, 2500\);\s+\/\/\s+=====\s+Profile Switcher UI\s+=====/g, replace: "el.classList.remove('show'); }, 2500);\n            }\n            // ===== Profile Switcher UI =====" },
    { match: /return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"\/><\/svg>';\s+\/\/\s+=====\s+Bot Builder\s+=====/g, replace: "return '<svg viewBox=\"0 0 24 24\" width=\"16\" height=\"16\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/></svg>';\n            }\n            // ===== Bot Builder =====" },
    { match: /\.catch\(\(\) => \{ setBtnLoading\(btn, false\); showToast\('خطا در ارتباط', 'error'\); \}\);\s+\};\s+<\/script>/g, replace: ".catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });\n            };\n        })();\n        </script>" }
];

for (let r of replacements) {
    let original = content;
    content = content.replace(r.match, r.replace);
    if (original === content) {
        console.log("Could not find match for regex:\n", r.match);
    }
}

fs.writeFileSync('includes/views/portal.php', content);
