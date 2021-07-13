<?php

namespace app\views\email;

use app\views\AbstractView;
use app\models\User;
use app\libraries\FileUtils;
use app\models\EmailStatusModel;

class EmailStatusView extends AbstractView {
    public function showEmailStatus($emailStatus) {
        $this->core->getOutput()->addBreadcrumb("Email Statuses", $this->core->buildCourseUrl(["email"]));
        return $this->renderStatusPage($email_status);
    }
    public function showSuperuserEmailStatus($emailStatuses) {
        $this->core->getOutput()->addBreadcrumb("Superuser Email Statuses", $this->core->buildUrl(["superuser","email"]));
        return $this->renderStatusPage($email_status);
    }
    private function renderStatusPage($email_status) {
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalCss('email-status.css');
        return $this->core->getOutput()->renderTwigTemplate("EmailStatusPage.twig", [
            "subjects" => $email_status->getSubjects(),
            "pending" => $email_status->getPending(),
            "successes" => $email_status->getSuccesses(),
            "errors" => $email_status->getErrors(),
            "courses" => $email_status->getCourses()
        ]);
    }
}
