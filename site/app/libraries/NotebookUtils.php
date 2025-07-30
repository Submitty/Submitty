<?php

declare(strict_types=1);

namespace app\libraries;

class NotebookUtils {
    private const TEXT_LIMIT = 1024 * 5; // 5KB limit for output text
    private const LINE_LIMIT = 100; // 100 lines limit for text output
    private const IMG_SIZE_LIMIT = 1024 * 1024 * 2; // 2MB limit for images
    private const NOTEBOOK_SIZE_LIMIT = 1024 * 1024 * 10; // 10MB limit for rendering
    // Note: SVG files are not supported due to XSS risks
    private const MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/bmp',
        'text/plain', // Fallback to text/plain if it is available.
    ];

    /**
     * Accepts a path to a .ipynb file and returns an array in the Submitty notebook format.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function jupyterToSubmittyNotebook(string $filepath): array {
        // Check the total file size before doing any processing
        if (filesize($filepath) > self::NOTEBOOK_SIZE_LIMIT) {
            // Return a single markdown cell with an error message
            return [
                [
                    'type' => 'markdown',
                    'markdown_data' => '### Notebook Not Rendered' . PHP_EOL .
                    'The notebook file is too large to be displayed (over ' . (self::NOTEBOOK_SIZE_LIMIT / (1024 * 1024)) . ' MB).' . PHP_EOL .
                    'Please download the notebook to view its contents.'
                ]
            ];
        }

        $filedata = FileUtils::readJsonFile($filepath);
        if ($filedata === false) {
            $filedata = [];
        }

        $cells = [];
        $skipped_content = 0;
        $skipped_output = 0;
        // Process each cell in the notebook
        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown':
                    $result = self::processMarkdownCell($cell);
                    $cells[] = $result['cell'];
                    $skipped_content += $result['skipped_content'];
                    break;
                case 'code':
                    $result = self::processCodeCell($cell, $filedata);
                    $cells = array_merge($cells, $result['cells']);
                    $skipped_output += $result['skipped_output'];
                    break;
                default:
                    break;
            }
        }

        // If there is skipped content, prepend a warning cell
        if ($skipped_content > 0 || $skipped_output > 0) {
            $warning_cell = [
                'type' => 'markdown',
                'markdown_data' => '### Notebook Rendered with Skipped Content' . PHP_EOL .
                'Some content was skipped due to size limits. ' .
                ($skipped_content > 0 ? "Skipped $skipped_content attachment(s)." : '') .
                ($skipped_output > 0 ? " Skipped $skipped_output output(s)." : '') .
                PHP_EOL . 'Download the notebook to view the full notebook.'
            ];
            array_unshift($cells, $warning_cell);
        }

        return $cells;
    }

    /**
     * Process a markdown cell and return the processed cell.
     *
     * @param array<string,mixed> $cell
     * @return array{
     *     cell: array<string,mixed>,
     *     skipped_content: int
     * }
     */
    private static function processMarkdownCell(array $cell): array {
        $skipped_content = 0;
        $markdown = is_array($cell['source']) ? implode($cell['source']) : (string) $cell['source'];
        $search = [];
        $replace = [];

        // Render attachment images (not HTML)
        if (isset($cell['attachments']) && count($cell['attachments']) > 0) {
            // Add attachments and a corresponding string to replace the attachment
            foreach ($cell['attachments'] as $filename => $attachment) {
                foreach ($attachment as $mime => $base64) {
                    $search[] = "attachment:$filename";
                    if (!in_array($mime, self::MIME_TYPES, true)) {
                        $replace[] = 'Image skipped: image type not supported.';
                        $skipped_content += 1;
                    }
                    elseif (strlen($base64) > self::IMG_SIZE_LIMIT) {
                        $replace[] = 'Image skipped: exceeds size limit of ' . (self::IMG_SIZE_LIMIT / (1024 * 1024)) . ' MB. Download the notebook to view the image.';
                        $skipped_content += 1;
                    }
                    else {
                        $data_uri = 'data:' . $mime . ";base64," . $base64;
                        $replace[] = $data_uri;
                    }
                }
            }
        }
        // Replace all attachments with the data URI or a message if skipped
        $markdown = str_replace($search, $replace, $markdown);
        return [
            'cell' => [
                'type' => 'markdown',
                'markdown_data' => $markdown,
            ],
            'skipped_content' => $skipped_content
        ];
    }

    /**
     * Process a code cell and return the processed cell.
     *
     * @param array<string,mixed> $cell
     * @param array<string,mixed> $filedata
     * @return array{
     *     cells: array<int,array<string,mixed>>,
     *     skipped_output: int
     * }
     */
    private static function processCodeCell(array $cell, array $filedata): array {
        $skipped_output = 0;
        $code_cell = [];
        $code_cell[] = [
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
            switch ($output['output_type'] ?? '') {
                // Print output text if it is a stream
                case 'stream':
                    $truncation_result = self::truncateText($output['text'] ?? '');
                    $output_text = $truncation_result['text'];
                    $skipped_output += $truncation_result['was_truncated'];
                    $code_cell[] = [
                        'type' => 'output',
                        'output_text' => $output_text,
                    ];
                    break;
                // Handle display data and execute result outputs
                case 'display_data':
                case 'execute_result':
                    $data = $output['data'] ?? [];
                    $output_type = null;
                    foreach (self::MIME_TYPES as $mime_type) {
                        if (isset($output['data'][$mime_type])) {
                            $output_type = $mime_type;
                            break;
                        }
                    }

                    $text = $output['data']['text/plain'] ?? '';
                    $truncation_result = self::truncateText($text);
                    $text = $truncation_result['text'];
                    $skipped_output += $truncation_result['was_truncated'];

                    if ($output_type === 'text/plain') {
                        // Display output text if we don't know how to render the content otherwise
                        $code_cell[] = [
                            'type' => 'output',
                            'output_text' => $text,
                        ];
                    }
                    elseif ($output_type !== null) {
                        // If we know how to render the content, create an image cell
                        $img = $data[$output_type];
                        if (strlen($img) > self::IMG_SIZE_LIMIT) {
                            $code_cell[] = [
                                'type' => 'output',
                                'output_text' => 'Image skipped: exceeds size limit of ' . (self::IMG_SIZE_LIMIT / (1024 * 1024)) . ' MB. Download the notebook to view the full image.'
                            ];
                            $skipped_output += 1;
                        }
                        else {
                            $code_cell[] = [
                                'type' => 'image',
                                'image' => "data:" . $output_type . ';base64, ' . $img,
                                'width' => 0,
                                'height' => 0,
                                'alt_text' => $text,
                            ];
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return [
            'cells' => $code_cell,
            'skipped_output' => $skipped_output
        ];
    }

    /**
     * Truncate text to the defined limit and append a message if truncated.
     *
     * @param string|string[] $text
     * @return array{
     *     text: string,
     *     was_truncated: int
     * }
     */
    private static function truncateText(string|array $text): array {
        $output_text = is_array($text) ? implode($text) : $text;
        $truncated_text = $output_text;
        $was_truncated = false;

        // Truncate by line limit if necessary
        $lines = explode("\n", $truncated_text);
        if (count($lines) > self::LINE_LIMIT) {
            $lines = array_slice($lines, 0, self::LINE_LIMIT);
            $truncated_text = implode("\n", $lines);
            $was_truncated = true;
        }

        // Truncate by character limit if necessary
        if (strlen($truncated_text) > self::TEXT_LIMIT) {
            $truncated_text = substr($truncated_text, 0, self::TEXT_LIMIT);
            $was_truncated = true;
        }

        // If truncation occurred, append the warning message
        if ($was_truncated) {
            $final_text = $truncated_text . '...' . PHP_EOL . '[Output truncated: exceeds size or line limit. Download the notebook to view the full output.]';
            return ['text' => $final_text, 'was_truncated' => 1];
        }

        return ['text' => $output_text, 'was_truncated' => 0];
    }
}
