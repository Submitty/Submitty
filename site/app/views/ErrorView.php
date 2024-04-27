<?php

namespace app\views;

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
    public function noAccessCourse(bool $can_rejoin_course, string $readd_url): string {
        return $this->core->getOutput()->renderTwigTemplate("error/NoAccessCourse.twig", [
            "course_name" => $this->core->getDisplayedCourseName(),
            "semester" => $this->core->getFullSemester(),
            "main_url" => $this->core->getConfig()->getBaseUrl(),
            "ability_to_readd" => $can_rejoin_course,
            "readd_url" => $readd_url,
            "csrf_token" => $this->core->getCsrfToken()
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
