import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const archiver = require('archiver');
console.log(typeof archiver);
console.log(Object.keys(archiver));
