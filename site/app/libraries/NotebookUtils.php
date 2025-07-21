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
        // Note: SVG files are not supported due to XSS risks
        $mime_types = [
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/bmp',
            'text/plain', // Fall back to text/plain if it is available.
        ];
        $img_size_limit = 1024 * 1024; // 1MB limit
        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown':
                    $markdown = is_array($cell['source']) ? implode($cell['source']) : (string) $cell['source'];
                    // Render attachment images (not HTML)
                    if (isset($cell['attachments']) && count($cell['attachments']) > 0) {
                        foreach ($cell['attachments'] as $filename => $attachment) {
                            foreach ($attachment as $mime => $base64) {
                                $log_message = '';
                                if (in_array($mime, $mime_types, true)) {
                                    if (strlen($base64) <= $img_size_limit) {
                                        $data_uri = 'data:' . $mime . ";base64," . $base64;
                                        $markdown = str_replace("attachment:$filename", $data_uri, $markdown);
                                    }
                                    else {
                                        $log_message = 'Image skipped: exceeds size limit of ' . $img_size_limit . ' bytes.';
                                        $markdown = $markdown . PHP_EOL . $log_message;
                                    }
                                }
                                else {
                                    $log_message = 'Image skipped: image type not supported.';
                                    $markdown = $markdown . PHP_EOL . $log_message;
                                }
                            }
                        }
                    }
                    $cells[] = [
                        'type' => 'markdown',
                        'markdown_data' => $markdown,
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
                        elseif ((($output['output_type'] ?? '') === 'display_data' || ($output['output_type'] ?? '') === 'execute_result') && isset($output['data'])) {
                            $log_message = '';
                            $output_type = null;
                            foreach ($mime_types as $mime_type) {
                                if (isset($output['data'][$mime_type])) {
                                    $output_type = $mime_type;
                                    break;
                                }
                            }

                            $text = $output['data']['text/plain'] ?? '';
                            $output_text = is_array($text) ? implode($text) : (string) $text;

                            if ($output_type === 'text/plain') {
                                // Display output text if we don't know how to render the content otherwise
                                $cells[] = [
                                    'type' => 'output',
                                    'output_text' => $output_text,
                                ];
                            }
                            elseif ($output_type !== null) {
                                $img = $output['data'][$output_type];
                                if (strlen($img) <= $img_size_limit) {
                                    $cells[] = [
                                        'type' => 'image',
                                        'image' => "data:" . $output_type . ';base64, ' . $img,
                                        'width' => 0,
                                        'height' => 0,
                                        'alt_text' => $output_text,
                                    ];
                                }
                                else {
                                    $log_message = 'Image skipped: exceeds size limit of ' . $img_size_limit . ' bytes.';
                                    $cells[] = [
                                        'type' => 'output',
                                        'output_text' => $log_message,
                                    ];
                                }
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
