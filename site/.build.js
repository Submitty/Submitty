const path = require('path');
const module_path = path.join(__dirname, 'ts');

function getAllFiles(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).reduce((acc, entry) => {
        if (!entry.isDirectory() && entry.name.endsWith('.ts') || entry.name.endsWith('.js')) {
            acc.push(path.join(dir, entry.name));
        }
        
        return acc;
    }, []);
}

const files = getAllFiles(module_path);

require('esbuild').build({
    entryPoints: files,
    bundle: true,
    minify: true,
    sourcemap: true,
    outdir: './public/mjs',
});
