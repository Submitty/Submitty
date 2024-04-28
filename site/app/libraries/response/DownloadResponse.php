<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class DownloadResponse
 * @package app\libraries\response
 */
class DownloadResponse implements ResponseInterface {
    /** @var array<mixed> json encoded array */
    public array $json;
    public string $title;
    public string $file_type;

    /**
     * DownloadResponse constructor.
     * Returns a JSON array
     * @param mixed|null $data
     */
    private function __construct(mixed $data = null, string $title = 'downloaded_file', string $file_type = 'text/plain') {
        $this->json = $data;
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->getOutput()->renderFile($this->json, $this->title, $this->file_type);
    }

    /**
     * Returns a DownloadResponse.
     * @param mixed|null $data
     * @return DownloadResponse
     */
    public static function getDownloadResponse(mixed $data = null, string $title = 'downloaded_file', string $file_type = 'text/plain'): DownloadResponse {
        return new self($data, $title, $file_type);
    }
}
