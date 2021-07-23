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

    public function renderStatusPage($email_status) {
        return $this->core->getOutput()->renderTwigTemplate("EmailStatus.twig", [
            "subjects" => $email_status->getSubjects(),
            "pending" => $email_status->getPending(),
            "successes" => $email_status->getSuccesses(),
            "errors" => $email_status->getErrors(),
            "courses" => $email_status->getCourses()
        ]);
    }
}
