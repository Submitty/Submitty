<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class RedirectResponse
 * @package app\libraries\response
 */
class RedirectResponse implements ResponseInterface {
    /** @var string */
    public $url;

    /**
     * RedirectResponse constructor.
     * @param string|null $url
     */
    public function __construct(?string $url) {
        $this->url = $url ?? "";
    }

    /**
     * Redirect to $this->url.
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->redirect($this->url);
    }
}
