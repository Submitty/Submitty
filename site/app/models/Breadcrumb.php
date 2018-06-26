<?php

namespace app\models;
use app\libraries\Core;

/**
 * Breadcrumb header navigation item
 *
 * @method string getString()
 * @method null getUrl()
 * @method bool isTop()
 * @method bool isIcon()
*/
class Breadcrumb extends AbstractModel {
    /** @property string $title */
    protected $title;
    /** @property null $url */
    protected $url = null;
    /** @property bool $top */
    protected $top = false;
    /** @property bool $icon */
    protected $icon = false;

    /**
     * Breadcrumb constructor.
     * @param Core $core
     * @param string $title
     * @param null $url
     * @param bool $top
     * @param bool $icon
     */
    public function __construct(Core $core, string $title, $url = null, bool $top = false, bool $icon = false) {
        parent::__construct($core);
        $this->title = $title;
        $this->url = $url;
        $this->top = $top;
        $this->icon = $icon;
    }

}