<?php

declare(strict_types=1);

namespace app\views\banner;

use app\libraries\FileUtils;
use app\models\User;
use app\views\AbstractView;
use app\views\NavigationView;

class BannerView extends AbstractView {
    /**
     * This function shows a calendar with arbitrary items. It first shows a calendar view that list all items on
     * @return string
     * calendar by their given date. Then it shows a series of tables to list all items.
     *
     */
    public function showBanner(): string {
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");

        return $this->core->getOutput()->renderTwigTemplate("banner/Banner.twig", ["csrf_token" => $this->core->getCsrfToken()]);
    }
}
