<?php

namespace app\views\grading;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\views\AbstractView;

class GradeableView extends AbstractView {

    public function renderComponentTable(Gradeable $gradeable, string $disabled) {

        //TODO: Put this into the model

        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        foreach ($gradeable->getComponents() as $component) {
            /* @var GradeableComponent $question */
            $question = $component;

            $page = intval($question->getPage());
            // if the page is determined by the student json
            if ($page == -1) {
                // usually the order matches the json
                if ($student_pages[intval($question->getOrder())]["order"] == intval($question->getOrder())) {
                    $page = intval($student_pages[intval($question->getOrder())]["page #"]);
                }
                // otherwise, iterate through until the order matches
                else {
                    foreach ($student_pages as $student_page) {
                        if ($student_page["order"] == intval($question->getOrder())) {
                            $page = intval($student_page["page #"]);
                            break;
                        }
                    }
                }
            }
        }
    }

}
