<?php

namespace app\views;

class ErrorView extends AbstractView {
    public function exceptionPage($error_message) {
        return $this->core->getOutput()->renderTwigTemplate("error/ExceptionPage.twig", [
            "error_message" => $error_message
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

    public function noAccessCourse() {
        return $this->core->getOutput()->renderTwigTemplate("error/NoAccessCourse.twig", [
            "course_name" => $this->core->getDisplayedCourseName(),
            "semester" => $this->core->getFullSemester(),
            "main_url" => $this->core->getConfig()->getBaseUrl()
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
