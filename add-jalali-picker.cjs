const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let newCode = `
// Jalali Calendar implementation
window.jalaliToGregorian = function(jy, jm, jd) {
    let sal_a, gy, gm, gd, days;
    jy += 1595;
    days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    gd = days + 1;
    sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return [gy, gm, gd];
};

window.gregorianToJalali = function(gy, gm, gd) {
    let g_d_m, jy, jm, jd, gy2, days;
    g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    gy2 = (gm > 2) ? (gy + 1) : gy;
    days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    jy = -1595 + (33 * Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    if (days < 186) {
        jm = 1 + Math.floor(days / 31);
        jd = 1 + (days % 31);
    } else {
        jm = 7 + Math.floor((days - 186) / 30);
        jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
};

window.renderJalaliPicker = function(prefix, displayId, hiddenId) {
    let pickerId = prefix + '_jalali_picker';
    let picker = document.getElementById(pickerId);
    if (!picker) return;

    if (!picker.innerHTML.trim()) {
        picker.innerHTML = \`
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <select id="\${prefix}_year" class="ssp-select" style="width:100px;" onchange="updateJalaliDays('\${prefix}')"></select>
                <select id="\${prefix}_month" class="ssp-select" style="width:120px;" onchange="updateJalaliDays('\${prefix}')"></select>
                <select id="\${prefix}_day" class="ssp-select" style="width:80px;"></select>
            </div>
            <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                <label class="ssp-label" style="margin:0; font-size:0.85rem;">ساعت:</label>
                <select id="\${prefix}_hour" class="ssp-select" style="width:70px;"></select>
                <label class="ssp-label" style="margin:0; font-size:0.85rem;">دقیقه:</label>
                <select id="\${prefix}_minute" class="ssp-select" style="width:70px;"></select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="ssp-btn-primary" onclick="confirmJalaliDate('\${prefix}', '\${displayId}', '\${hiddenId}')" style="font-size:0.85rem;">تایید</button>
                <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('\${pickerId}').style.display='none'" style="font-size:0.85rem;">انصراف</button>
            </div>
        \`;
    }

    let ySel = document.getElementById(prefix + '_year');
    let mSel = document.getElementById(prefix + '_month');
    let hSel = document.getElementById(prefix + '_hour');
    let minSel = document.getElementById(prefix + '_minute');

    let now = new Date();
    let jNow = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());

    if (ySel.options.length === 0) {
        for (let i = jNow[0]; i <= jNow[0] + 5; i++) {
            ySel.add(new Option(i, i));
        }
        let mNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        for (let i = 1; i <= 12; i++) mSel.add(new Option(mNames[i-1], i));
        for (let i = 0; i < 24; i++) hSel.add(new Option(String(i).padStart(2, '0'), i));
        for (let i = 0; i < 60; i += 5) minSel.add(new Option(String(i).padStart(2, '0'), i));
        
        ySel.value = jNow[0];
        mSel.value = jNow[1];
        hSel.value = now.getHours();
        minSel.value = Math.floor(now.getMinutes() / 5) * 5;
    }
    
    updateJalaliDays(prefix, jNow[2]);
    picker.style.display = 'block';
};

window.updateJalaliDays = function(prefix, defaultDay = null) {
    let ySel = document.getElementById(prefix + '_year');
    let mSel = document.getElementById(prefix + '_month');
    let dSel = document.getElementById(prefix + '_day');
    if (!ySel || !mSel || !dSel) return;
    
    let m = parseInt(mSel.value);
    let y = parseInt(ySel.value);
    let days = 30;
    if (m <= 6) days = 31;
    else if (m === 12) {
        // basic leap year check
        let r = y % 33;
        days = (r===1 || r===5 || r===9 || r===13 || r===17 || r===22 || r===26 || r===30) ? 30 : 29;
    }
    
    let currentDay = defaultDay || parseInt(dSel.value) || 1;
    dSel.innerHTML = '';
    for (let i = 1; i <= days; i++) {
        dSel.add(new Option(i, i));
    }
    if (currentDay > days) currentDay = days;
    dSel.value = currentDay;
};

window.confirmJalaliDate = function(prefix, displayId, hiddenId) {
    let y = parseInt(document.getElementById(prefix + '_year').value);
    let m = parseInt(document.getElementById(prefix + '_month').value);
    let d = parseInt(document.getElementById(prefix + '_day').value);
    let h = parseInt(document.getElementById(prefix + '_hour').value);
    let min = parseInt(document.getElementById(prefix + '_minute').value);
    
    let g = jalaliToGregorian(y, m, d);
    
    let gStr = g[0] + '-' + String(g[1]).padStart(2, '0') + '-' + String(g[2]).padStart(2, '0') + ' ' + String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0') + ':00';
    let jStr = y + '/' + String(m).padStart(2, '0') + '/' + String(d).padStart(2, '0') + ' ' + String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0');
    
    document.getElementById(hiddenId).value = gStr;
    document.getElementById(displayId).value = jStr;
    document.getElementById(prefix + '_jalali_picker').style.display = 'none';
};

// Bind specific stubs
window.manualOpenJalaliPicker = function() {
    renderJalaliPicker('manual', 'manual_schedule_display', 'manual_schedule_datetime');
};
window.schOpenJalaliPicker = function() {
    renderJalaliPicker('sch', 'sch_schedule_date_display', 'sch_schedule_datetime');
};
window.pgOpenJalaliPicker = function() {
    renderJalaliPicker('pg', 'pg_schedule_date_display', 'pg_schedule_datetime');
};
`;

core = core.replace(/window\.manualOpenJalaliPicker = function\(\) \{[\s\S]*?\};/, '');
core = core.replace(/window\.schOpenJalaliPicker = function\(\) \{[\s\S]*?\};/, '');
core = core.replace(/window\.pgOpenJalaliPicker = function\(\) \{[\s\S]*?\};/, '');
core = core.replace(/window\.pgConfirmJalaliDate = function\(\) \{[\s\S]*?\};/, '');
core = core.replace(/window\.pgConfirmSchedule = function\(\) \{[\s\S]*?\};/, "window.pgConfirmSchedule = function() { let btn = document.getElementById('pg_schedule_btn'); if(btn) btn.click(); };");

core = core + newCode;
fs.writeFileSync('assets/js/portal-core.js', core);
