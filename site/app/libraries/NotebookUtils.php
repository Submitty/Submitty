<?php

declare(strict_types=1);

namespace app\libraries;

class NotebookUtils {
    private const TEXT_LIMIT = 1024 * 5; // 5KB limit for output text
    private const LINE_LIMIT = 250; // 250 lines limit for text output
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
                    'The executed notebook is larger than ' . (self::NOTEBOOK_SIZE_LIMIT / (1024 * 1024)) . ' MB and will not be displayed.' . PHP_EOL .
                    'Please download the notebook to view its contents.'
                ]
            ];
        }

        $filedata = FileUtils::readJsonFile($filepath);
        if ($filedata === false) {
            $filedata = [];
        }

        $cells = [];
        // Number of skipped markdown images
        $skipped_content_count = 0;
        // Number of skipped output from code cells
        $skipped_output_count = 0;
        // Process each cell in the notebook
        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown':
                    $result = self::parseMarkdownCell($cell);
                    $cells[] = $result['cell'];
                    $skipped_content_count += $result['skipped_content_count'];
                    break;
                case 'code':
                    $result = self::parseCodeCell($cell, $filedata);
                    $cells = array_merge($cells, $result['cells']);
                    $skipped_output_count += $result['skipped_output_count'];
                    break;
                default:
                    break;
            }
        }

        // If there is skipped content, prepend a warning cell
        if ($skipped_content_count > 0 || $skipped_output_count > 0) {
            $warning_cell = [
                'type' => 'markdown',
                'markdown_data' => '### Notebook Rendered with Skipped Content' . PHP_EOL .
                'Some content was skipped due to size limits. ' .
                ($skipped_content_count > 0 ? "Skipped $skipped_content_count attachment(s)." : '') .
                ($skipped_output_count > 0 ? " Skipped $skipped_output_count output(s)." : '') .
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
     *     skipped_content_count: int
     * }
     */
    private static function parseMarkdownCell(array $cell): array {
        $skipped_content_count = 0;
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
                        $skipped_content_count += 1;
                    }
                    elseif (strlen($base64) > self::IMG_SIZE_LIMIT) {
                        $replace[] = 'Image skipped: exceeds size limit of ' . (self::IMG_SIZE_LIMIT / (1024 * 1024)) . ' MB. Download the notebook to view the image.';
                        $skipped_content_count += 1;
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
            'skipped_content_count' => $skipped_content_count
        ];
    }

    /**
     * Process a code cell and return the processed cell.
     *
     * @param array<string,mixed> $cell
     * @param array<string,mixed> $filedata
     * @return array{
     *     cells: array<int,array<string,mixed>>,
     *     skipped_output_count: int
     * }
     */
    private static function parseCodeCell(array $cell, array $filedata): array {
        $skipped_output_count = 0;
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
                    $skipped_output_count += $truncation_result['was_truncated'];
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
                    $skipped_output_count += $truncation_result['was_truncated'];

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
                            $skipped_output_count += 1;
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
            'skipped_output_count' => $skipped_output_count
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
        // Standardize line endings
        $normalized_text = str_replace(["\r\n", "\r"], "\n", $output_text);

        $output_text_length = strlen($output_text);
        $line_limit_pos = $output_text_length;
        $pos = -1;
        // Find the position of the last newline within the text limit
        for ($i = 0; $i < self::LINE_LIMIT; $i++) {
            $pos = strpos($normalized_text, "\n", $pos + 1);
            if ($pos === false) {
                break;
            }
        }

        if ($pos !== false) {
            $line_limit_pos = $pos;
        }

        // Determine the position to truncate based on the line limit and text limit
        $truncate_pos = min($line_limit_pos, self::TEXT_LIMIT);
        $was_truncated = $truncate_pos < $output_text_length;

        // If truncation occurred, append the warning message
        $truncated_text = $was_truncated ? substr($output_text, 0, $truncate_pos) . PHP_EOL . '...' . PHP_EOL . '[Output truncated: exceeds size or line limit. Download the notebook to view the full output.]' : $output_text;

        return ['text' => $truncated_text, 'was_truncated' => $was_truncated ? 1 : 0];
    }
}
