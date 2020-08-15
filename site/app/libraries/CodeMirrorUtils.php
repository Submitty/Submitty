<?php

namespace app\libraries;

class CodeMirrorUtils {

    const DEFAULT_CSS_FILES = [
        'codemirror/codemirror.css',
        'codemirror/theme/eclipse.css',
        'codemirror/theme/monokai.css'
    ];

    const DEFAULT_JS_FILES = [
        'codemirror/codemirror.js',
        'codemirror/addon/display/placeholder.js',
        'codemirror/mode/clike/clike.js',
        'codemirror/mode/haskell/haskell.js',
        'codemirror/mode/haskell-literate/haskell-literate.js',
        'codemirror/mode/javascript/javascript.js',
        'codemirror/mode/octave/octave.js',
        'codemirror/mode/oz/oz.js',
        'codemirror/mode/python/python.js',
        'codemirror/mode/scheme/scheme.js',
        'codemirror/mode/shell/shell.js',
        'codemirror/mode/sql/sql.js',
    ];

    const DEFAULT_MIME_TYPE = 'text/plain';

    /**
     * Defines a map between languages (keys) that submitty developers/users have been using and the mime-types (values)
     * that CodeMirror uses for their mode.
     */
    const MIME_TYPE_MAP = [
        'bash' => 'text/x-sh',
        'c' => 'text/x-csrc',
        'clike' => 'text/x-csrc',
        'c++' => 'text/x-c++src',
        'c#' => 'text/x-csharp',
        'cpp' => 'text/x-c++src',
        'haskell' => 'text/x-haskell',
        'haskell-literate' => 'text/x-literate-haskell',
        'h' => 'text/x-c++src',
        'hpp' => 'text/x-c++src',
        'java' => 'text/x-java',
        'javascript' => 'text/javascript',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'matlab' => 'text/x-octave',
        'mysql' => 'text/x-mysql',
        'node' => 'text/javascript',
        'nodejs' => 'text/javascript',
        'objective-c' => 'text/x-objectivec',
        'oz' => 'text/x-oz',
        'pgsql' => 'text/x-pgsql',
        'postgres' => 'text/x-pgsql',
        'postgresql' => 'text/x-pgsql',
        'python' => 'text/x-python',
        'scala' => 'text/x-scala',
        'scheme' => 'text/x-scheme',
        'sh' => 'text/x-sh',
        'sql' => 'text/x-sql',
        'typescript' => 'text/typescript'
    ];

    /**
     * Get an array of languages for codemirror that are available in submitty.
     *
     * @return array The list of languages available to use with CodeMirror.
     */
    public static function getLanguages(): array {
        return array_keys(self::MIME_TYPE_MAP);
    }

    /**
     * Given a language find the corresponding codemirror mime-type.
     *
     * @param string|null $language The name of a programming language or null
     * @return string The corresponding mime-type, or a default mime-type if there is no corresponding mime-type.
     */
    public static function getCodeMirrorMode(?string $language): string {
        if (is_null($language)) {
            return self::DEFAULT_MIME_TYPE;
        }

        return self::MIME_TYPE_MAP[strtolower($language)] ?? self::DEFAULT_MIME_TYPE;
    }

    /**
     * Load the js/css codemirror dependencies we have defined above as constants
     *
     * @param Core $core
     */
    public static function loadDefaultDependencies(Core $core): void {
        $core->getOutput()->addInternalJs('code-mirror-utils.js');

        foreach (self::DEFAULT_JS_FILES as $file) {
            $core->getOutput()->addVendorJs($file);
        }

        foreach (self::DEFAULT_CSS_FILES as $file) {
            $core->getOutput()->addVendorCss($file);
        }
    }
}
