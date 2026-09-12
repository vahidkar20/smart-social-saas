const fs = require('fs');
let css = fs.readFileSync('assets/css/portal.css', 'utf8');

// I will add extremely premium css on top
css += `
/* ========================================================
   ULTIMATE PREMIUM UI REDESIGN
   ======================================================== */
:root {
    --bg: #F8FAFC;
    --bg-alt: #F1F5F9;
    --card: #FFFFFF;
    --text: #0F172A;
    --text-muted: #64748B;
    --text-subtle: #94A3B8;
    --border: #E2E8F0;
    --border-hover: #CBD5E1;
    --accent: #4F46E5;
    --accent-hover: #4338CA;
    --accent-soft: #EEF2FF;
    --success: #059669;
    --success-soft: #ECFDF5;
    --warning: #D97706;
    --warning-soft: #FFFBEB;
    --danger: #E11D48;
    --danger-soft: #FFF1F2;
    --info: #0284C7;
    --info-soft: #F0F9FF;
    --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
}

body.ssp-page-active {
    background-color: var(--bg-alt);
    font-family: 'IRANSansX', 'Vazirmatn', system-ui, sans-serif !important;
}

.ssp-wrap {
    box-shadow: var(--shadow-lg);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: var(--bg);
}

.ssp-sidebar {
    background: #ffffff;
    border-left: 1px solid var(--border);
    padding: 24px 16px;
}

.ssp-drawer-item {
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 4px;
    color: var(--text-muted);
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ssp-drawer-item:hover {
    background: var(--bg-alt);
    color: var(--text);
    transform: translateX(-4px);
}
.ssp-drawer-item.active {
    background: var(--accent);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    font-weight: 700;
}

.ssp-card, .ssp-item-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.ssp-card:hover, .ssp-item-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--border-hover);
    transform: translateY(-2px);
}

.ssp-btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #6366F1 100%);
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    transition: all 0.2s;
}
.ssp-btn-primary:hover {
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
    transform: translateY(-1px);
    background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent) 100%);
}

.ssp-input, .ssp-textarea, .ssp-select {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 16px;
    background: var(--bg-alt);
    color: var(--text);
    transition: all 0.2s;
    font-family: inherit;
}
.ssp-input:focus, .ssp-textarea:focus, .ssp-select:focus {
    background: var(--card);
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.ssp-label {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
    display: inline-block;
}

h2 {
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text);
    font-size: 1.75rem;
}

.ssp-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ssp-badge.success { background: var(--success-soft); color: var(--success); }
.ssp-badge.info { background: var(--info-soft); color: var(--info); }
.ssp-badge.warning { background: var(--warning-soft); color: var(--warning); }
.ssp-badge.danger { background: var(--danger-soft); color: var(--danger); }

/* Remove emojis from placeholders and labels completely */
::-webkit-input-placeholder { /* Edge */
    color: var(--text-subtle);
}
:-ms-input-placeholder { /* Internet Explorer 10-11 */
    color: var(--text-subtle);
}
::placeholder {
    color: var(--text-subtle);
}
`;

fs.writeFileSync('assets/css/portal.css', css);
