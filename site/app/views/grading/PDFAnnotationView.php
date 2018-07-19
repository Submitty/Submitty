<?php

namespace app\views\grading;

use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Component;
use app\models\User;
use app\views\AbstractView;

class PDFAnnotationView extends AbstractView {
    public function showAnnotationPage(){
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $return = $this->core->getOutput()->renderTwigTemplate("grading/electronic/PDFAnnotationFullPage.twig", [

        ]);
        return $return;
    }
}
