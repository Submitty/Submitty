<?php


namespace app\libraries\response;

use app\libraries\Core;


/**
 * Class RedirectResponse
 * @package app\libraries\response
 */
class RedirectResponse extends AbstractResponse {
    /** @var string */
    public $url;

    /**
     * RedirectResponse constructor.
     * @param $url
     */
    public function __construct($url) {
        $this->url = $url;
    }

    /**
     * Redirect to $this->url.
     * @param Core $core
     */
    public function render(Core $core) {
        $core->redirect($this->url);
    }
}