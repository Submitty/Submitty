<?php

namespace app\models;
use app\libraries\Core;

/**
 * Breadcrumb navigation item
 *
 * @method string getTitle()
 * @method string|null getUrl()
 * @method bool isExternalLink()
*/
class Breadcrumb extends AbstractModel {
    /** @property string */
    protected $title;
    /** @property string|null */
    protected $url = null;
    /** @property string|null */
    protected $external_url = false;

    /**
     * Breadcrumb constructor.
     * @param Core $core
     * @param string $title
     * @param string|null $url
     * @param string|null $external_url
     */
    public function __construct(Core $core, string $title, $url = null, $external_url = null) {
        parent::__construct($core);
        $this->title = $title;
        $this->url = $url;
        $this->external_url = $external_url;
    }

}
