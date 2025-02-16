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
                        'markdown_data' => is_array($cell['source']) ? implode($cell['source']) : (string) $cell['source'],
                    ];
                    break;
                case 'code':
                    $cells[] = [
                        'type' => 'short_answer',
                        'label' => '',
                        'programming_language' => $filedata['metadata']['language_info']['name'] ?? 'python',
                        'initial_value' => is_array($cell['source']) ? implode($cell['source']) : (string) $cell['source'],
                        'rows' => is_array($cell['source']) ? count($cell['source']) : 1,
                        'filename' => $cell['id'] ?? 'notebook-cell-' . rand(),
                        'recent_submission' => '',
                        'version_submission' => '',
                        'codemirror_mode' => $filedata['language_info']['codemirror_mode']['name'] ?? 'ipython',
                    ];

                    foreach ($cell['outputs'] ?? [] as $output) {
                        if (($output['output_type'] ?? '') === 'stream') {
                            $cells[] = [
                                'type' => 'output',
                                'output_text' => is_array($output['text'] ?? '') ? implode($output['text']) : (string) ($output['text'] ?? ''),
                            ];
                        }
                        elseif (($output['output_type'] ?? '') === 'display_data' && isset($output['data'])) {
                            // Note: SVG files are not supported due to XSS risks
                            $mime_types = [
                                'image/png',
                                'image/jpeg',
                                'image/gif',
                                'image/bmp',
                                'text/plain', // Fall back to text/plain if it is available.
                            ];

                            $output_type = null;
                            foreach ($mime_types as $mime_type) {
                                if (isset($output['data'][$mime_type])) {
                                    $output_type = $mime_type;
                                    break;
                                }
                            }

                            if ($output_type === 'text/plain') {
                                // Display output text if we don't know how to render the content otherwise
                                $cells[] = [
                                    'type' => 'output',
                                    'output_text' => $output['data']['text/plain'],
                                ];
                            }
                            elseif ($output_type !== null) {
                                $cells[] = [
                                    'type' => 'image',
                                    'image' => 'data:' . $output_type . ';base64, ' . $output['data'][$output_type],
                                    'width' => 0,
                                    'height' => 0,
                                    'alt_text' => $output['data']['text/plain'] ?? '',
                                ];
                            }
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
