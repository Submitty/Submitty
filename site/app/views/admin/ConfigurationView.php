<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig(
        array $fields,
        $gradeable_seating_options,
        bool $email_enabled,
        array $submitty_admin_user,
        string $csrf_token
    ) {
        $this->output->addInternalJs("configuration.js");
        $this->output->addInternalCss("configuration.css");
        $this->output->addBreadcrumb('Course Settings');
        return $this->output->renderTwigTemplate("admin/Configuration.twig", [
            "fields" => $fields,
            "gradeable_seating_options" => $gradeable_seating_options,
            "submitty_admin_user" => $submitty_admin_user,
            "theme_url" => $this->core->buildCourseUrl(['theme']),
            "email_enabled" => $email_enabled,
            "email_room_seating_url" => $this->core->buildCourseUrl(['email_room_seating']),
            "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories']),
            "csrf_token" => $csrf_token,
        ]);
    }
}
