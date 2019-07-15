<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields, $gradeable_seating_options, $theme_url, $email_room_seating_url) {
        $this->core->getOutput()->addInternalJs("course-settings.js");
        $manage_categories_url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_categories'));
        return $this->core->getOutput()->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "gradeable_seating_options" => $gradeable_seating_options,
            "theme_url" => $theme_url,
            "email_room_seating_url" => $email_room_seating_url,
            "manage_categories_url" => $manage_categories_url,
            "csrf_token" => $this->core->getCsrfToken(),
            "email_enabled" => $this->core->getConfig()->isEmailEnabled()
        ]);
    }
}
