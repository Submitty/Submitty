<?php

declare(strict_types=1);
namespace app\libraries;

class NotebookUtils {
    /**
     * Accepts a path to a .ipynb file and returns an array in the Submitty notebook format.
     */
    public static function jupyterToSubmittyNotebook(string $filepath): array {
        $filedata = FileUtils::readJsonFile($filepath);
        if ($filedata === false) {
            $filedata = [];
        }

        $cells = [];

        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown': {
                    $cells[] = [
                        'type' => 'markdown',
                        'markdown_data' => implode($cell['source']),
                    ];
                    break;
                }
                case 'code': {
                    $cells[] = [
                        'type' => 'short_answer',
                        'label' => '',
                        'programming_language' => $filedata['metadata']['language_info']['name'] ?? 'python',
                        'initial_value' => implode($cell['source']),
                        'rows' => count($cell['source']),
                        'filename' => $cell['id'],
                        'recent_submission' => '',
                        'version_submission' => '',
                        'codemirror_mode' => $filedata['language_info']['codemirror_mode']['name'] ?? 'ipython',
                    ];
                    break;
                }
                default:
                    break;
            }
        }

        return $cells;
    }
}
