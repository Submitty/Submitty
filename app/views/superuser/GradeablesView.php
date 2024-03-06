<?php

declare(strict_types=1);

namespace app\views\superuser;

use app\libraries\GradeableType;
use app\models\gradeable\GradeableList;
use app\views\AbstractView;
use app\views\NavigationView;

class GradeablesView extends AbstractView {
    public function showGradeablesList(\app\models\gradeable\GradeableList $gradeable_list) {
        $render_sections = [];
        $gradeable_list_sections = [
            GradeableList::OPEN => $gradeable_list->getOpenGradeables(),
            GradeableList::GRADING => $gradeable_list->getGradingGradeables(),
        ];

        foreach ($gradeable_list_sections as $section => $gradeables) {
            $render_section = NavigationView::gradeableSections[$section];
            $render_section['gradeables'] = [];
            foreach (array_reverse($gradeables, true) as $id => $gradeable) {
                /** @var \app\models\gradeable\Gradeable $gradeable */
                $render_section['gradeables'][$id] = [
                    'title' => $gradeable->getTitle(),
                    'url' => $gradeable->getInstructionsUrl(),
                    'submission' => ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate() : '',
                    'grading_open' => $gradeable->getGradeStartDate(),
                    'grading_due' => $gradeable->getGradeDueDate()
                ];
            }
            $render_sections[] = $render_section;
        }

        $this->core->getOutput()->addInternalCss("navigation.css");
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb("Pending Gradeables");

        return $this->core->getOutput()->renderTwigTemplate("superuser/Gradeables.twig", [
            "gradeable_sections" => $render_sections
        ]);
    }
}
