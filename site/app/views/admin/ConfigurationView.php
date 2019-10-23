<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields, $gradeable_seating_options, $categories, $email_enabled, $csrf_token) {
        $this->output->addInternalJs("configuration.js");
        $this->output->addInternalCss("configuration.css");
        $this->output->addBreadcrumb('Course Settings');
        return $this->output->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "gradeable_seating_options" => $gradeable_seating_options,
            "categories_empty" => $categories,
            "theme_url" => $this->core->buildCourseUrl(['theme']),
            "email_room_seating_url" => $this->core->buildCourseUrl(['email_room_seating']),
            "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories']),
            "csrf_token" => $csrf_token,
            "email_enabled" => $email_enabled
        ]);
    }
}
