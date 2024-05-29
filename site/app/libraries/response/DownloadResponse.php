<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class DownloadResponse
 * @package app\libraries\response
 */
class DownloadResponse implements ResponseInterface {
    public string $contents;
    public string $title;
    public string $file_type;

    /**
     * DownloadResponse constructor.
     * @param string $data
     * @param string $title
     * @param string $file_type
     */
    private function __construct(string $data = '', string $title = 'downloaded_file', string $file_type = 'text/plain') {
        $this->contents = $data;
        $this->title = $title;
        $this->file_type = $file_type;
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->getOutput()->renderFile($this->contents, $this->title, $this->file_type);
    }

    /**
     * Returns a DownloadResponse.
     * @param string $data
     * @param string $title
     * @param string $file_type
     * @return DownloadResponse
     */
    public static function getDownloadResponse(string $data = '', string $title = 'downloaded_file', string $file_type = 'text/plain'): DownloadResponse {
        return new self($data, $title, $file_type);
    }
}
