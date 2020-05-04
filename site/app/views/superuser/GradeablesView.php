<?php

declare(strict_types=1);

namespace app\views\superuser;

use app\libraries\GradeableType;
use app\models\gradeable\GradeableList;
use app\views\AbstractView;
use app\views\NavigationView;

class GradeablesView extends AbstractView {
    const DATE_FORMAT = "m/d/Y @ h:i A T";

    public function showGradeablesList(\app\models\gradeable\GradeableList $gradeable_list) {
        $render_sections = [];
        foreach ($gradeable_list->getGradeablesBySection() as $section => $gradeables) {
            $render_section = NavigationView::gradeableSections[$section];
            $render_section['gradeables'] = [];
            foreach ($gradeables as $id => $gradeable) {
                $render_section['gradeables'][$id] = [
                    'title' => $gradeable->getTitle(),
                    'url' => $gradeable->getInstructionsUrl(),
                    'submission' => '',
                    'grading' => 'GRADING OPENS ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT)
                ];
                // opens <>
                // grading opens <>
                if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                    if ($section < GradeableList::OPEN) {
                        $render_section['gradeables'][$id]['open'] = "OPENS " . $gradeable->getSubmissionOpenDate()->format(self::DATE_FORMAT);
                    }
                    else {
                        $prefix = ($section === GradeableList::OPEN) ? 'CLOSES' : 'CLOSED';
                        $render_section['gradeables'][$id]['open'] = $prefix . " " . $gradeable->getSubmissionDueDate()->format(self::DATE_FORMAT);
                    }
                }
                if ($section >= GradeableList::GRADING) {
                    $render_section['gradeables'][$id]['grading'] = 'GRADING CLOSED ' . $gradeable->getGradeDueDate()->format(self::DATE_FORMAT);
                }
            }
            $render_sections[] = $render_section;
        }

        $this->core->getOutput()->addInternalCss("navigation.css");
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate("superuser/Gradeables.twig", [
            "gradeable_sections" => $render_sections
        ]);
    }
}
