<?php

namespace app\views;
use app\controllers\admin\ConfigurationController;

class ErrorView extends AbstractView {
    public function exceptionPage($error_message) {
        return $this->core->getOutput()->renderTwigTemplate("error/ExceptionPage.twig", [
            "error_message" => $error_message
        ]);
    }

    public function courseErrorPage($error_message) {
        return $this->core->getOutput()->renderTwigTemplate("error/CourseErrorPage.twig", [
            "error_message" => $error_message,
            "course_url" => $this->core->buildCourseUrl(),
        ]);
    }

    public function errorPage($error_message) {
        return $this->core->getOutput()->renderTwigTemplate("error/ErrorPage.twig", [
            "error_message" => $error_message,
            "main_url" => $this->core->getConfig()->getBaseUrl()
        ]);
    }

    public function noGradeable($gradeable_id = null) {
        return $this->core->getOutput()->renderTwigTemplate("error/InvalidGradeable.twig", [
            "gradeable_id" => $gradeable_id
        ]);
    }

    /**
     * Creates the No Course Access page.
     * @param bool $can_rejoin_course True if the student meets the conditions to rejoin the
     *  course if they so wish.
     * @param string $readd_url URL to the rejoin course function.
     * @return string The Twig HTML for this page.
     */
    public function noAccessCourse(bool $can_rejoin_course, string $readd_url, int $self_registration_type, ?int $default_section_id): string {
        return $this->core->getOutput()->renderTwigTemplate("error/NoAccessCourse.twig", [
            "course_name" => $this->core->getDisplayedCourseName(),
            "semester" => $this->core->getFullSemester(),
            "course_id" => $this->core->getConfig()->getCourse(),
            "main_url" => $this->core->getConfig()->getBaseUrl(),
            "course_home_url" => $this->core->getConfig()->getCourseHomeUrl(),
            "ability_to_readd" => $can_rejoin_course,
            "readd_url" => $readd_url,
            "register_url" => $this->core->buildCourseUrl(['register']),
            "user" => $this->core->getUser(),
            "csrf_token" => $this->core->getCsrfToken(),
            "self_registration_type" => $self_registration_type,
            "all_self_register" => ConfigurationController::ALL_SELF_REGISTER,
            "default_section_id" => $default_section_id
        ]);
    }

    public function unbuiltGradeable($gradeable_title) {
        return $this->core->getOutput()->renderTwigTemplate('error/UnbuiltGradeable.twig', [
            'title' => $gradeable_title
        ]);
    }

    public function genericError(array $error_messages) {
        return $this->core->getOutput()->renderTwigTemplate('error/GenericError.twig', [
            'error_messages' => $error_messages
        ]);
    }
}
