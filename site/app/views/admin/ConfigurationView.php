<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields, $gradeable_seating_options) {
        $this->core->getOutput()->addInternalJs("course-settings.js");
        $this->core->getOutput()->addBreadcrumb('Course Settings');
        return $this->core->getOutput()->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "gradeable_seating_options" => $gradeable_seating_options,
            "theme_url" => $this->core->buildNewCourseUrl(['theme']),
            "email_room_seating_url" => $this->core->buildNewCourseUrl(['email_room_seating']),
            "manage_categories_url" => $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_categories')),
            "csrf_token" => $this->core->getCsrfToken(),
            "email_enabled" => $this->core->getConfig()->isEmailEnabled()
        ]);
    }
}
