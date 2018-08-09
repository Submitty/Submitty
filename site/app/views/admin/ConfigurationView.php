<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields, $gradeable_seating_options) {
        $this->core->getOutput()->addInternalJs("course-settings.js");
        return $this->core->getOutput()->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "gradeable_seating_options" => $gradeable_seating_options
        ]);
    }
}
