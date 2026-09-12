const fs = require('fs');

const fixCors = (filePath) => {
    if (!fs.existsSync(filePath)) return;
    let code = fs.readFileSync(filePath, 'utf8');
    
    // Check if it already uses HTTP_ORIGIN
    if (code.includes('HTTP_ORIGIN')) return;

    const oldCors = "header('Access-Control-Allow-Origin: *');";
    const newCors = `$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';\n    header('Access-Control-Allow-Origin: ' . $origin);`;
    
    if (code.includes(oldCors)) {
        code = code.replace(newCors, oldCors); // reverse if accidentally applied
        code = code.replace(oldCors, newCors);
        fs.writeFileSync(filePath, code);
        console.log('Patched CORS in ' + filePath);
    }
};

fixCors('includes/ssp-cors.php');
fixCors('includes/class-ssp-core.php');
fixCors('includes/class-ssp-ai-browser.php');
