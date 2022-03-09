const fs = require('fs');
const path = require('path');
const module_path = path.join(__dirname, 'ts');

function getTwigFiles(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).reduce((acc, entry) => {
        if (entry.isDirectory()) {
            acc.push(...getTwigFiles(path.join(dir, entry.name)));
        } else if (entry.name.endsWith('.ts') || entry.name.endsWith('.js')) {
            acc.push(path.join(dir, entry.name));
        }
        
        return acc;
    }, []);
}

function getAllFiles(dir) {
    return getTwigFiles(path.join(dir, "twig")).concat(fs.readdirSync(path.join(dir, "module"), { withFileTypes: true }).reduce((acc, entry) => {
        if (!entry.isDirectory() && entry.name.endsWith('.ts') || entry.name.endsWith('.js')) {
            acc.push(path.join(dir, "module", entry.name));
        }
        
        return acc;
    }, []));
}

const files = getAllFiles(module_path);

require('esbuild').build({
    entryPoints: files,
    bundle: true,
    format: "esm",
    minify: true,
    sourcemap: true,
    splitting: true,
    outdir: path.join(__dirname, 'public', 'mjs'),
});
