<?php

declare(strict_types=1);

namespace app\views\banner;

use app\libraries\FileUtils;
use app\views\AbstractView;
use app\entities\banner\BannerImage;

class BannerView extends AbstractView {
    /**
     * Shows banners
     * @param array<BannerImage> $communityEventImages
     *
     */
    public function showEventBanners(array $communityEventImages): string {
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));

        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $this->core->getOutput()->addInternalJs("banner.js");

        return $this->core->getOutput()->renderTwigTemplate("banner/Banner.twig", [
            "csrf_token" => $this->core->getCsrfToken(),
            "banners" => $communityEventImages,

        ]);
    }
}
