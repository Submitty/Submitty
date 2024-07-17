<?php

declare(strict_types=1);

namespace app\libraries;

class NotebookUtils {
    /**
     * Accepts a path to a .ipynb file and returns an array in the Submitty notebook format.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function jupyterToSubmittyNotebook(string $filepath): array {
        $filedata = FileUtils::readJsonFile($filepath);
        if ($filedata === false) {
            $filedata = [];
        }

        $cells = [];

        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown':
                    $cells[] = [
                        'type' => 'markdown',
                        'markdown_data' => implode($cell['source']),
                    ];
                    break;
                case 'code':
                    $cells[] = [
                        'type' => 'short_answer',
                        'label' => '',
                        'programming_language' => $filedata['metadata']['language_info']['name'] ?? 'python',
                        'initial_value' => implode($cell['source']),
                        'rows' => count($cell['source']),
                        'filename' => $cell['id'] ?? 'notebook-cell-' . rand(),
                        'recent_submission' => '',
                        'version_submission' => '',
                        'codemirror_mode' => $filedata['language_info']['codemirror_mode']['name'] ?? 'ipython',
                    ];

                    foreach ($cell['outputs'] ?? [] as $output) {
                        if (($output['output_type'] ?? '') === 'stream') {
                            $cells[] = [
                                'type' => 'output',
                                'output_text' => implode($output['text'] ?? []),
                            ];
                        }
                    }

                    break;
                default:
                    break;
            }
        }

        return $cells;
    }
}
