const fs = require('fs');
const { exec } = require("child_process");

const module_path = './public/ts/';

let files = [];
let ts_files = "";

function getAllFiles(path){
    fs.readdirSync(path, {withFileTypes:true}).forEach((file) => {
        if(file.isDirectory()){
            return getAllFiles(path + file['name'] + '/');
        }

        filename = file['name'];
        if(!filename.includes('.js') && !filename.includes('.ts')){
            return;
        }

        files.push(path + filename);
    });
}

getAllFiles(module_path);

require('esbuild').build({
    entryPoints: files,
    bundle: true,
    minify: true,
    sourcemap: true,
    outdir: './public/mjs'
});
