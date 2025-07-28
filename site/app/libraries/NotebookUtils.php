<?php

declare(strict_types=1);

namespace app\libraries;

class NotebookUtils {
    private const TEXT_LIMIT = 1024 * 30; // 30KB limit for output text
    private const IMG_SIZE_LIMIT = 1024 * 1024 * 5; // 5MB limit for images
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
        foreach ($filedata['cells'] as $cell) {
            switch ($cell['cell_type']) {
                case 'markdown':
                    $cells[] = self::processMarkdownCell($cell);
                    break;
                case 'code':
                    $cells = array_merge($cells, self::processCodeCell($cell, $filedata));
                    break;
                default:
                    break;
            }
        }

        return $cells;
    }

    /**
     * Process a markdown cell and return the processed cell.
     *
     * @param array<string,mixed> $cell
     * @return array<string,mixed>
     */
    private static function processMarkdownCell(array $cell): array {
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
                    }
                    elseif (strlen($base64) > self::IMG_SIZE_LIMIT) {
                        $replace[] = 'Image skipped: exceeds size limit of ' . (self::IMG_SIZE_LIMIT / (1024 * 1024)) . ' MB. Download the notebook to view the image.';
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
            'type' => 'markdown',
            'markdown_data' => $markdown,
        ];
    }

    /**
     * Process a code cell and return the processed cell.
     *
     * @param array<string,mixed> $cell
     * @param array<string,mixed> $filedata
     * @return array<int,array<string,mixed>>
     */
    private static function processCodeCell(array $cell, array $filedata): array {
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
                    $output_text = is_array($output['text'] ?? '') ? implode($output['text']) : (string) ($output['text'] ?? '');
                    if (strlen($output_text) > self::TEXT_LIMIT) {
                        $output_text = self::truncateText($output_text);
                    }
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
                    if ($output_type === 'text/plain') {
                        // Display output text if we don't know how to render the content otherwise
                        $code_cell[] = [
                            'type' => 'output',
                            'output_text' => self::truncateText($text),
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
                        }
                        else {
                            $code_cell[] = [
                                'type' => 'image',
                                'image' => "data:" . $output_type . ';base64, ' . $img,
                                'width' => 0,
                                'height' => 0,
                                'alt_text' => self::truncateText($text),
                            ];
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $code_cell;
    }

    /**
     * Truncate text to the defined limit and append a message if truncated.
     *
     * @param string|string[] $text
     * @return string
     */
    private static function truncateText(string|array $text): string {
        $output_text = is_array($text) ? implode($text) : $text;
        if (strlen($output_text) > self::TEXT_LIMIT) {
            return substr($output_text, 0, self::TEXT_LIMIT) . '...' . PHP_EOL . '[Output truncated: exceeds size limit of ' . (self::TEXT_LIMIT / 1024) . ' KB. Download the notebook to view the full output.]';
        }
        return $output_text;
    }
}
