const fs = require('fs');
let css = fs.readFileSync('assets/css/portal.css', 'utf8');

css += `
/* Further UI/UX Modernization */
body.ssp-page-active {
    font-family: 'IRANSansX', system-ui, -apple-system, sans-serif !important;
    background-color: var(--bg-alt);
}
.ssp-sidebar {
    background: var(--card);
    border-left: 1px solid var(--border);
    box-shadow: 2px 0 15px rgba(0,0,0,0.02);
}
.ssp-btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
    box-shadow: 0 4px 12px var(--accent-soft);
    border: none;
    color: #ffffff;
    font-weight: 600;
}
.ssp-btn-primary:hover {
    box-shadow: 0 6px 16px var(--accent-soft);
    transform: translateY(-1px);
}
.ssp-btn-secondary {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    font-weight: 600;
}
.ssp-btn-secondary:hover {
    background: var(--bg-alt);
    border-color: var(--border-hover);
}
.ssp-card {
    background: var(--card);
    border-radius: 16px;
    border: 1px solid rgba(226, 232, 240, 0.6);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
}
.ssp-card:hover {
    box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.05), 0 8px 12px -6px rgba(0, 0, 0, 0.03);
}
.ssp-header {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}
.ssp-theme-dark .ssp-header {
    background: rgba(30, 41, 59, 0.8);
    border-bottom: 1px solid rgba(51, 65, 85, 0.6);
}
.ssp-tab-btn.active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 700;
    border-right: 3px solid var(--accent);
}
.ssp-drawer-item.active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 700;
}
.ssp-drawer-group-title {
    letter-spacing: 0.5px;
    opacity: 0.7;
    margin-top: 10px;
}
`;

fs.writeFileSync('assets/css/portal.css', css);
