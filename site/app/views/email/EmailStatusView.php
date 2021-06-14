<?php

namespace app\views\email;

use app\views\AbstractView;
use app\models\User;
use app\libraries\FileUtils;
use app\models\EmailStatusModel;

class EmailStatusView extends AbstractView {
    public function showEmailStatus($emailStatus) {
        $this->core->getOutput()->addBreadcrumb("Email Statuses", $this->core->buildCourseUrl(["email"]));
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalCss('email-status.css');
        return $this->core->getOutput()->renderTwigTemplate("EmailStatusPage.twig", [
            "subjects" => $emailStatus->getSubjects(),
            "pending" => $emailStatus->getPending(),
            "successes" => $emailStatus->getSuccesses(),
            "errors" => $emailStatus->getErrors()
        ]);
    }
    
    public function showSuperuserEmailStatus($emailStatuses) {
        $this->core->getOutput()->addBreadcrumb("Superuser Email Statuses", $this->core->buildUrl(["superuser","email"]));
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalCss('email-status.css');
        return $this->core->getOutput()->renderTwigTemplate("superuser/SuperuserEmailStatus.twig", [
            "subjects" => $emailStatuses->getSubjects(),
            "pending" => $emailStatuses->getPending(),
            "successes" => $emailStatuses->getSuccesses(),
            "errors" => $emailStatuses->getErrors(),
            "courses" => $emailStatuses->getCourses()
        ]);
    }
}
