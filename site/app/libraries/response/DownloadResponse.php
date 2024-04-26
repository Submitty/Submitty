<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class DownloadResponse
 * @package app\libraries\response
 */
class DownloadResponse {
    /** @var array<mixed> json encoded array */
    public $json;

    /**
     * DownloadResponse constructor.
     * Returns a JSON array
     * @param mixed|null $data
     */
    private function __construct(mixed $data = null) {
        $this->json = $data;
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->getOutput()->renderJson($this->json);
    }

    /**
     * Returns a DownloadResponse.
     * @param mixed|null $data
     * @return DownloadResponse
     */
    public static function getDownloadResponse(mixed $data = null): DownloadResponse {
        return new self($data);
    }
}
