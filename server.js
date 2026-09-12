import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import { ZipArchive } from 'archiver';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

// Serve static files from public directory
app.use(express.static(path.join(__dirname, 'public')));

app.get('/api/download', (req, res) => {
  const pluginFolderName = 'smart-social-saas-refactored';
  res.attachment(`${pluginFolderName}.zip`);
  
  const archive = new ZipArchive({
    zlib: { level: 9 } // Sets the compression level.
  });

  archive.on('error', (err) => {
    console.error("Archive Error:", err);
    res.status(500).send({error: err.message});
  });

  archive.on('end', function() {
    console.log('Archive wrote %d bytes', archive.pointer());
  });

  archive.pipe(res);

  // Exclude node_modules, package.json, server.js, public, etc.
  const ignoreList = [
    'node_modules/**',
    'public/**',
    'package.json',
    'package-lock.json',
    'server.js',
    'bun.lock',
    '.git/**',
    '*.cjs'
  ];

  // We append all files into a subfolder named 'smart-social-saas-refactored'
  archive.glob('**/*', { 
    cwd: __dirname, 
    ignore: ignoreList,
    nodir: true 
  }, { prefix: pluginFolderName }); // This line wraps everything in the folder

  archive.finalize();
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Server running on port ${PORT}`);
});
