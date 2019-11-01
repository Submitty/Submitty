<?php

namespace app\models;

use app\libraries\Core;

/**
 * Breadcrumb navigation item
 *
 * These are used to give a user a sense of where they are in the hierarchy of
 * the site. Each breadcrumb has a title, and then optionally a link and an
 * external link. The links are used to provide an anchor tag on the title, while
 * if there's an external link, then it shows a fa-external-link icon that has
 * an anchor tag pointing to the external url.
 *
 * @link https://fontawesome.com/v4.7.0/icon/external-link
 * @link https://fontawesome.com/start
 * @link https://use.fontawesome.com/releases/v5.6.3/css/solid.css
 * @link https://use.fontawesome.com/releases/v5.6.3/css/regular.css
 * @link https://use.fontawesome.com/releases/v5.6.3/css/brands.css
 * @link https://use.fontawesome.com/releases/v5.6.3/css/fontawesome.css
 *
 * @method string getTitle()
 * @method string|null getUrl()
 * @method string|null getExternalUrl()
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
