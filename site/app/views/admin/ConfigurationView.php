<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields) {
        return $this->core->getOutput()->renderTwigTemplate("admin/Configuration.twig", ["fields" => $fields]);
    }
}
