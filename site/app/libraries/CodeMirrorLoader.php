<?php

namespace app\libraries;

class CodeMirrorLoader {

    const DEFAULT_CSS_FILES = [
        'codemirror/codemirror.css',
        'codemirror/theme/eclipse.css',
        'codemirror/theme/monokai.css'
    ];

    const DEFAULT_JS_FILES = [
        'codemirror/codemirror.js',
        'codemirror/mode/clike/clike.js',
        'codemirror/mode/cmake/cmake.js',
        'codemirror/mode/commonlisp/commonlisp.js',
        'codemirror/mode/css/css.js',
        'codemirror/mode/erlang/erlang.js',
        'codemirror/mode/haskell/haskell.js',
        'codemirror/mode/haskell-literate/haskell-literate.js',
        'codemirror/mode/htmlembedded/htmlembedded.js',
        'codemirror/mode/htmlmixed/htmlmixed.js',
        'codemirror/mode/javascript/javascript.js',
        'codemirror/mode/markdown/markdown.js',
        'codemirror/mode/oz/oz.js',
        'codemirror/mode/pascal/pascal.js',
        'codemirror/mode/perl/perl.js',
        'codemirror/mode/php/php.js',
        'codemirror/mode/python/python.js',
        'codemirror/mode/r/r.js',
        'codemirror/mode/ruby/ruby.js',
        'codemirror/mode/rust/rust.js',
        'codemirror/mode/shell/shell.js',
        'codemirror/mode/sql/sql.js',
        'codemirror/mode/xml/xml.js',
        'codemirror/mode/yaml/yaml.js',
    ];

    public static function loadDefaultDependencies(Core $core): void {
        foreach (self::DEFAULT_CSS_FILES as $file) {
            $core->getOutput()->addVendorCss($file);
        }

        foreach (self::DEFAULT_JS_FILES as $file) {
            $core->getOutput()->addVendorJs($file);
        }
    }
}