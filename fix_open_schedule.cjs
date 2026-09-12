const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// The openNewScheduleForDate can be called without arguments from the button:
// onclick="openNewScheduleForDate()"
// We need to handle this case properly and fallback to current date.

const replacement = `window.openNewScheduleForDate = function(jy, jm, jd, gDateStr) {
    let now = new Date();
    let hours = String(now.getHours()).padStart(2, '0');
    let minutes = '00';
    
    // Fallback if no args are passed
    if (!jy || !jm || !jd || !gDateStr) {
        if (typeof gregorianToJalali === 'function') {
            let j = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
            jy = j[0]; jm = j[1]; jd = j[2];
        } else {
            jy = 1400; jm = 1; jd = 1; // dummy fallback
        }
        gDateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
    }

    let jStr = \`\${jy}/\${String(jm).padStart(2,'0')}/\${String(jd).padStart(2,'0')} \${hours}:\${minutes}\`;
    let gStr = \`\${gDateStr} \${hours}:\${minutes}:00\`;

    // Switch to schedules tab and prefill
    if (typeof switchTab === 'function') {
        let btn = document.querySelector('[data-tab="schedules"]');
        switchTab('schedules', btn);
    }

    let dispEl = document.getElementById('sch_schedule_date_display');
    let dtEl = document.getElementById('sch_schedule_datetime');
    if (dispEl) dispEl.value = jStr;
    if (dtEl) dtEl.value = gStr;

    let titleEl = document.getElementById('schedule_title');
    if (titleEl) {
        titleEl.focus();
        titleEl.scrollIntoView({ behavior: 'smooth' });
    }
};`;

code = code.replace(/window\.openNewScheduleForDate = function\(jy, jm, jd, gDateStr\) \{[\s\S]*?(?=window\.showAddScheduleModal = function\(\))/g, replacement + "\n\n");

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed openNewScheduleForDate function');
