<?php

namespace app\views\email;

use app\views\AbstractView;
use app\models\User;
use app\libraries\FileUtils;
use app\models\EmailStatusModel;

class EmailStatusView extends AbstractView {
    public function showEmailStatus($emailStatus){
        $this->core->getOutput()->addBreadcrumb("Email", $this->core->buildCourseUrl(["email"]));
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalCss('bootstrap.css');
        $this->core->getOutput()->addInternalCss('email-status.css');
        $this->core->getOutput()->addInternalJs('email-status.js');
        //$this->core->getOutput()->addVendorCss(FileUtils::joinpaths('bootstrap', 'css', 'bootstrap.min.css'));
        return $this->core->getOutput()->renderTwigTemplate("EmailStatusPage.twig", [
            "data" => $emailStatus->getData()
        ]); 
    }

}