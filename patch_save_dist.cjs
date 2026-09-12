const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `    let timesInput = document.getElementById('dist_schedule_times');
    let times = timesInput ? timesInput.value : '10:00';`;

let replacement = `    let timesArr = [];
    document.querySelectorAll('.dist-time-input').forEach(el => {
        if(el.value) timesArr.push(el.value);
    });
    let times = timesArr.join(',');
    
    let typeEl = document.getElementById('dist_schedule_type');
    let scheduleType = typeEl ? typeEl.value : 'daily';
`;

js = js.replace(target, replacement);

let target2 = `    fd.append('only_new', onlyNew);`;
let replacement2 = `    fd.append('only_new', onlyNew);
    fd.append('schedule_type', scheduleType);
    fd.append('schedule_times', times);
    fd.append('new_delay', delayVal);
    fd.append('new_delay_unit', delayUnit);
    if(catEl) fd.append('filter_categories', catEl.value);
    if(tagEl) fd.append('filter_tags', tagEl.value);
    if(minPriceEl) fd.append('filter_min_price', minPriceEl.value);
    if(maxPriceEl) fd.append('filter_max_price', maxPriceEl.value);
    if(stockEl) fd.append('filter_stock', stockEl.value);
    if(sortEl) fd.append('sort', sortEl.value);
    if(tplEl) fd.append('template', tplEl.value);
    if(imgEl) fd.append('include_image', imgEl.checked ? 1 : 0);
    fd.append('targets', JSON.stringify(targets));
`;

js = js.replace(target2, replacement2);

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched saveDistribution');
