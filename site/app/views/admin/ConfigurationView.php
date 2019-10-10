<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields, $csrf_token) {
        $this->output->addInternalJs("configuration.js");
        $this->output->addInternalCss("configuration.css");
        $this->output->addBreadcrumb('Course Settings');
        return $this->output->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "theme_url" => $this->core->buildCourseUrl(['theme']),
            "email_room_seating_url" => $this->core->buildCourseUrl(['email_room_seating']),
            "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories']),
            "csrf_token" => $csrf_token,
        ]);
    }
}
