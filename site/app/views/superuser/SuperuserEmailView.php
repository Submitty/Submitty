<?php

namespace app\views\superuser;

use app\views\AbstractView;

class SuperuserEmailView extends AbstractView {
    public function showEmailPage($num_faculty, $num_instructor, $num_full_access, $num_limited_access, $num_student) {
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb("Send System Wide Email");
        $this->core->getOutput()->addInternalCss('superuser-email.css');
        $this->core->getOutput()->addInternalJs('superuser-email.js');
        return $this->core->getOutput()->renderTwigTemplate("superuser/Email.twig", [
            "sendUrl" => $this->core->buildUrl(["superuser", "email", "send"]),
            "num_faculty" => $num_faculty,
            "num_instructor" => $num_instructor,
            "num_full_access" => $num_full_access,
            "num_limited_access" => $num_limited_access,
            "num_student" => $num_student
        ]);
    }
}
