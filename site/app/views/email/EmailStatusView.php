<?php

namespace app\views\email;

use app\views\AbstractView;
use app\models\User;
use app\libraries\FileUtils;
use app\models\EmailStatusModel;

class EmailStatusView extends AbstractView {
    public function showEmailStatusPage($num_page, $load_page_url) {
        $this->core->getOutput()->addBreadcrumb("Email Statuses", $this->core->buildCourseUrl(["email"]));
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalCss('email-status.css');
        $this->core->getOutput()->addInternalJs('email-status.js');
        return $this->core->getOutput()->renderTwigTemplate("EmailStatusPage.twig", [
            "num_page" => $num_page,
            "load_page_url" => $load_page_url
        ]);
    }

    public function showSuperuserEmailStatus($email_status) {
        $this->core->getOutput()->addBreadcrumb("Superuser Email Statuses", $this->core->buildUrl(["superuser","email"]));
        return $this->renderStatusPage($email_status);
    }

    public function renderStatusPage($data) {
        // A map of all unique subjects of emails to the time created
        $subjects = [];
        // A map of email subjects to the rows that are still pending to send in the database
        $pending = [];
        // A map of email subjects to the rows that successfully sent in the database
        $successes = [];
        // A map of email subjects to the rows that resulted in an error in the database
        $errors = [];
        // A map of email subjects to the semester and course as one string
        $courses = [];

        foreach ($data as $row) {
            $key = $this->EmailToKey($row);
            if (!in_array($key, $subjects)) {
                $subjects[] = $key;
                $successes[$key] = [];
                $errors[$key] = [];
                $pending[$key] = [];
            }
            if ($row->getSemester() != null || $row->getCourse() != null) {
                $courses[$key] = $row->getSemester() . ' ' . $row->getCourse();
            }
            if ($row->getSent() != null) {
                $successes[$key][] = $row;
            }
            elseif ($row->getError() != null) {
                $errors[$key][] = $row;
            }
            else {
                $pending[$key][] = $row;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("EmailStatus.twig", [
            "subjects" => $subjects,
            "pending" => $pending,
            "successes" => $successes,
            "errors" => $errors,
            "courses" => $courses
        ]);
    }

    private function EmailToKey($row) {
        return $row->getSubject() . ', ' . $row->getCreated()->format('Y-m-d H:i:s');
    }
}
