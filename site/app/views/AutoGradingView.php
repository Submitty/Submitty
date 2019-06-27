<?php

namespace app\views;

use app\models\Gradeable;
use app\models\gradeable\AutoGradedTestcase;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Component;
use app\models\gradeable\Mark;
use app\models\gradeable\TaGradedGradeable;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\DateUtils;

class AutoGradingView extends AbstractView {

    /**
     * @param AutoGradedVersion $version_instance
     * @param bool $show_hidden True to show the scores of hidden testcases
     * @param bool $show_hidden_details True to show the details of hidden testcases
     * @return string
     */
    public function showResults(AutoGradedVersion $version_instance, bool $show_hidden = false, bool $show_hidden_details = false) {
        $graded_gradeable = $version_instance->getGradedGradeable();
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();

        $has_badges = false;
        $nonhidden_earned = 0;
        $nonhidden_max = 0;
        $hidden_earned = 0;
        $hidden_max = 0;
        $show_hidden_breakdown = false;
        $any_visible_hidden = false;
        $num_visible_testcases = 0;

        // This variable should be false if autograding results
        // (files/database values) exist, but true if the assignment
        // is in the queue or something went wrong with autograding
        // (it crashed, files were corrupted, etc)
        $incomplete_autograding = !$version_instance->isAutogradingComplete();

        $testcase_array = array_map(function (AutoGradedTestcase $testcase) {
            $testcase_config = $testcase->getTestcase();
            return [
                'name' => $testcase_config->getName(),
                'hidden' => $testcase_config->isHidden(),
                'details' => $testcase_config->getDetails(),
                'has_points' => $testcase_config->getPoints() !== 0,
                'extra_credit' => $testcase_config->isExtraCredit(),
                'view_testcase_message' => $testcase_config->canViewTestcaseMessage(),
                'points_total' => $testcase_config->getPoints(),

                'has_extra_results' => $testcase->hasAutochecks(),
                'points' => $testcase->getPoints(),
                'can_view' => $testcase->canView(),
                'testcase_message' => $testcase->getMessage()
            ];
        }, $version_instance->getTestcases());

        if ($autograding_config->getTotalNonHidden() >= 0) {
            $has_badges = true;

            $nonhidden_earned = $version_instance->getNonHiddenPoints();
            $nonhidden_max = $autograding_config->getTotalNonHiddenNonExtraCredit();
            $hidden_earned = $version_instance->getTotalPoints();
            $hidden_max = $autograding_config->getTotalNonExtraCredit();

            if ($gradeable->isTaGradeReleased()) {
                foreach ($version_instance->getTestcases() as $testcase) {
                    if (!$testcase->canView()) continue;
                    if ($testcase->getTestcase()->isHidden()) {
                        $any_visible_hidden = true;
                        break;
                    }
                }
            }

            $show_hidden_breakdown = $any_visible_hidden && $show_hidden &&
                ($version_instance->getNonHiddenNonExtraCredit() + $version_instance->getHiddenNonExtraCredit() > $autograding_config->getTotalNonHiddenNonExtraCredit());
        }
        // testcases should only be visible if autograding is complete
        if(!$incomplete_autograding) {
            foreach ($version_instance->getTestcases() as $testcase) {

                if ($testcase->canView()) {
                    $num_visible_testcases++;
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("autograding/AutoResults.twig", [
            'gradeable_id' => $gradeable->getId(),
            'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            "num_visible_testcases" => $num_visible_testcases,
            "incomplete_autograding" => $incomplete_autograding,
            "show_hidden_breakdown" => $show_hidden_breakdown,
            "nonhidden_earned" => $nonhidden_earned,
            "nonhidden_max" => $nonhidden_max,
            "hidden_earned" => $hidden_earned,
            "hidden_max" => $hidden_max,
            "show_hidden" => $show_hidden,
            "show_hidden_details" => $show_hidden_details,
            "has_badges" => $has_badges,
            'testcases' => $testcase_array,
            'is_ta_grade_released' => $gradeable->isTaGradeReleased(),
            'display_version' => $version_instance->getVersion()
        ]);
    }

    /**
     * @param \app\models\gradeable\GradedGradeable $graded_gradeable
     * @param AutoGradedVersion $version version to display
     * @param AutoGradedTestcase $testcase testcase to display
     * @param $popup_css_file
     * @param string $who
     * @param bool $show_hidden
     * @return string
     * @throws \Exception
     */
    public function loadAutoChecks(\app\models\gradeable\GradedGradeable $graded_gradeable, AutoGradedVersion $version, AutoGradedTestcase $testcase, $popup_css_file, $who, $show_hidden = false) {
        if ($testcase->getTestcase()->isHidden() && !$show_hidden) {
            return "";
        }

        $gradeable = $graded_gradeable->getGradeable();
        $checks = [];

        foreach ($testcase->getAutochecks() as $autocheck) {
            $description = $autocheck->getDescription();
            $diff_viewer = $autocheck->getDiffViewer();
            $file_path = $diff_viewer->getActualFilename();
            if (substr($file_path, strlen($file_path) - 4, 4) == ".pdf") {
                $public = $autocheck->getPublic();
                $file_name = pathinfo($file_path, PATHINFO_BASENAME);
                $file_path = urlencode($file_path);
                $checks[] = [
                    "pdf" => true,
                    "name" => $file_name,
                    "path" => $file_path,
                    "url" => $this->core->buildUrl([
                        "component" => "misc",
                        "page" => "display_file",
                        "dir" => $public ? "results_public": "results",
                        "file" => $file_name,
                        "path" => $file_path
                    ])
                ];
            } else {
                $check = [
                    "messages" => $autocheck->getMessages(),
                    "description" => $description
                ];
                $actual_title = "";
                if ($diff_viewer->hasDisplayExpected() || $diff_viewer->getActualFilename() != "") {
                    $actual_title = "Student ";
                }
                $actual_title .= $description;

                $actual_image = $diff_viewer->getActualImageFilename();
                $actual_display = $diff_viewer->getDisplayActual();
                if ($actual_image != "") {
                    $check["actual"] = [
                        "type" => "image",
                        "title" => $actual_title,
                        "show_popup" => false,
                        "src" => $this->autoGetImageSrc($actual_image),
                    ];
                } else if ($diff_viewer->hasDisplayActual()) {
                    if($autocheck->isDisplayAsSequenceDiagram()){
                        $check["actual"] = [
                            "type" => "sequence_diagram",
                            "title" => $actual_title,
                            "show_popup" => $this->autoShouldDisplayPopup($actual_display),
                            "src" => file_get_contents($diff_viewer->getActualFilename())
                        ];
                    }else{
                        $check["actual"] = [
                            "type" => "text",
                            "title" => $actual_title,
                            "show_popup" => $this->autoShouldDisplayPopup($actual_display),
                            "src" => $actual_display,
                        ];
                    }
                }

                $expected_image = $diff_viewer->getExpectedImageFilename();
                $expected_display = $diff_viewer->getDisplayExpected();
                $expected_title = "Expected {$description}";
                if ($expected_image != "") {
                    $check["expected"] = [
                        "type" => "image",
                        "title" => $expected_title,
                        "show_popup" => false,
                        "src" => $this->autoGetImageSrc($expected_image)
                    ];
                } else if ($diff_viewer->hasDisplayExpected()) {
                    $check["expected"] = [
                        "type" => "text",
                        "title" => $expected_title,
                        "show_popup" => $this->autoShouldDisplayPopup($expected_display),
                        "src" => $expected_display
                    ];
                }

                $difference_image = $diff_viewer->getDifferenceFilename();
                $difference_title = "Difference {$description}";
                if ($difference_image != "") {
                    $check["difference"] = [
                        "type" => "image",
                        "title" => $difference_title,
                        "show_popup" => false,
                        "src" => $this->autoGetImageSrc($difference_image)
                    ];
                }

                $checks[] = $check;
            }

            $diff_viewer->destroyViewer();
        }

        return $this->core->getOutput()->renderTwigTemplate("autograding/AutoChecks.twig", [
            "gradeable_id" => $gradeable->getId(),
            "checks" => $checks,
            "display_version" => $version->getVersion(),
            "index" => $testcase->getTestcase()->getIndex(),
            "who" => $who,
            "popup_css_file" => $popup_css_file,
        ]);
    }

    /**
     * @param string $display
     * @return string
     */
    private function autoShouldDisplayPopup(string $display): string {
        $tmp_array_string = explode("\n", trim(html_entity_decode(strip_tags($display)), "\xC2\xA0\t"));
        $less_than_30 = true;
        $arr_count = count($tmp_array_string);
        for ($x = 0; $x < $arr_count; $x++) {
            if (strlen($tmp_array_string[$x]) > 30) {
                $less_than_30 = false;
                $x = $arr_count;
            }
        }
        if (substr_count($display, 'line_number') < 10 && $less_than_30) {
            return false;
        }

        return true;
    }

    /**
     * @param string $path
     * @return string
     */
    private function autoGetImageSrc(string $path): string {
        // borrowed from file-display.php
        $content_type = FileUtils::getContentType($path);
        if (substr($content_type, 0, 5) === "image") {
            // Read image path, convert to base64 encoding
            $imageData = base64_encode(file_get_contents($path));
            // Format the image SRC:  data:{mime};base64,{data};
            return 'data: ' . mime_content_type($path) . ';charset=utf-8;base64,' . $imageData;
        }
        return ''; // ?
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    public function showTAResults(Gradeable $gradeable) {
        $grading_complete = true;
        $active_same_as_graded = true;
        foreach ($gradeable->getComponents() as $component) {
            if (!$component->getGrader()) {
                $grading_complete = false;
            }
            if ($component->getGradedVersion() !== $gradeable->getActiveVersion() && $component->getGradedVersion() !== -1) {
                $active_same_as_graded = false;
            }
        }
        $grader_names = array();
        //find all names of instructors who graded part(s) of this assignment that are full access grader_names
        if (!$gradeable->getPeerGrading()) {
            foreach ($gradeable->getComponents() as $component) {
                if ($component->getGrader() === NULL) {
                    continue;
                }
                $name = $component->getGrader()->getDisplayedFirstName() . " " . $component->getGrader()->getDisplayedLastName();
                if (!in_array($name, $grader_names) && $component->getGrader()->accessFullGrading()) {
                    $grader_names[] = $name;
                }
            }
        } else {
            $grader_names = "Graded by Peer(s)";
        }
        //get total score and max possible score
        $graded_score = $gradeable->getGradedTAPoints();
        $graded_max = $gradeable->getTotalTAPoints();
        //change title if autograding exists or not
        //display a sum of autograding and instructor points if both exist
        $has_autograding = false;
        foreach ($gradeable->getTestcases() as $testcase) {
            if ($testcase->viewTestcase()) {
                $has_autograding = true;
                break;
            }
        }
        // Todo: this is a lot of math for the view
        //add total points if both autograding and instructor grading exist
        $current = $gradeable->getCurrentVersion() === NULL ? $gradeable->getVersions()[1] : $gradeable->getCurrentVersion();
        $total_score = $current->getNonHiddenTotal() + $current->getHiddenTotal() + $graded_score;
        $total_max = $gradeable->getTotalAutograderNonExtraCreditPoints() + $graded_max;
        $regrade_enabled = $this->core->getConfig()->isRegradeEnabled();
        $regrade_message = $this->core->getConfig()->getRegradeMessage();
        //Clamp full gradeable score to zero
        $total_score = max($total_score, 0);
        $regrade_date=DateUtils::dateTimeToString($gradeable->getRegradeRequestDate());
        $num_decimals = strlen(substr(strrchr((string)$gradeable->getPointPrecision(), "."), 1));
        $allow_regrade= $gradeable->isRegradeOpen();
        $uploaded_files = $gradeable->getSubmittedFiles();
        return $this->core->getOutput()->renderTwigTemplate("autograding/TAResults.twig", [
            "gradeable" => $gradeable,
            "grader_names" => $grader_names,
            "grading_complete" => $grading_complete,
            "has_autograding" => $has_autograding,
            "graded_score" => $graded_score,
            "graded_max" => $graded_max,
            "total_score" => $total_score,
            "total_max" => $total_max,
            "active_same_as_graded" => $active_same_as_graded,
            "allow_regrade" => $allow_regrade,
            "regrade_date" => $regrade_date,
            "regrade_message" => $regrade_message,
            "num_decimals" => $num_decimals,
            "uploaded_files" => $uploaded_files
        ]);
    }

    /**
     * @param TaGradedGradeable $ta_graded_gradeable
     * @param bool $regrade_available
     * @param array $uploaded_files
     * @return string
     */
    public function showTAResultsNew(TaGradedGradeable $ta_graded_gradeable, bool $regrade_available, array $uploaded_files) {
        $gradeable = $ta_graded_gradeable->getGradedGradeable()->getGradeable();
        $active_version = $ta_graded_gradeable->getGradedGradeable()->getAutoGradedGradeable()->getActiveVersion();
        $version_instance = $ta_graded_gradeable->getGradedVersionInstance();
        $grading_complete = true;
        $active_same_as_graded = true;
        foreach ($gradeable->getComponents() as $component) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            if (!$container->isComplete()) {
                $grading_complete = false;
                continue;
            }

            if ($container->getGradedVersion() !== $active_version) {
                $active_same_as_graded = false;
            }
        }

        // Get the names of all full access or above graders
        $grader_names = array_map(function (User $grader) {
            return $grader->getDisplayedFirstName() . ' ' . $grader->getDisplayedLastName();
        }, $ta_graded_gradeable->getVisibleGraders());

        // Special messages for peer / mentor-only grades
        if ($gradeable->isPeerGrading()) {
            $grader_names = ['Graded by Peer(s)'];
        } else if (count($grader_names) === 0) {
            // Non-peer assignment with only limited access graders
            $grader_names = ['Course Staff'];
        }

        //get total score and max possible score
        $total_score = $graded_score = $ta_graded_gradeable->getTotalScore();
        $total_max = $graded_max = $gradeable->getTaPoints();

        //change title if autograding exists or not
        //display a sum of autograding and instructor points if both exist
        $has_autograding = $gradeable->getAutogradingConfig()->anyVisibleTestcases();

        // Todo: this is a modest amount of math for the view
        // add total points if autograding and ta grading are the same version consistently
        if ($version_instance !== null) {
            $total_score += $version_instance->getTotalPoints();
            $total_max += $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();
        }
        $regrade_message = $this->core->getConfig()->getRegradeMessage();
        //Clamp full gradeable score to zero
        $total_score = max($total_score, 0);
        $total_score = $gradeable->roundPointValue($total_score);

        $late_days_url = $this->core->buildUrl([
            'component' => 'student',
            'page' => 'view_late_table',
            'g_id' => $gradeable->getId()
        ]);
        $regrade_allowed = $gradeable->isRegradeAllowed();
        $regrade_date = $gradeable->getRegradeRequestDate();
        $regrade_date=DateUtils::dateTimeToString($gradeable->getRegradeRequestDate());
        // Get the number of decimal places for floats to display nicely
        $num_decimals = 0;
        $precision_parts = explode('.', strval($gradeable->getPrecision()));
        if (count($precision_parts) > 1) {
            // TODO: this hardcoded value will mean a weird precision value (like 1/3) won't appear ridiculous
            $num_decimals = min(3, count($precision_parts));
        }

        $component_data = array_map(function (Component $component) use ($ta_graded_gradeable) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            return [
                'title' => $component->getTitle(),
                'extra_credit' => $component->isExtraCredit(),
                'points_possible' => $component->getMaxValue(),
                'student_comment' => $component->getStudentComment(),

                'total_score' => $container->getTotalScore(),
                'custom_mark_score' => $container->getScore(),
                'comment' => $container->getComment(),
                'graders' => array_map(function (User $grader) {
                    return $grader->getDisplayedLastName();
                }, $container->getVisibleGraders()),
                'marks' => array_map(function (Mark $mark) use ($container) {
                    return [
                        'title' => $mark->getTitle(),
                        'points' => $mark->getPoints(),

                        'show_mark' => $mark->isPublish() || $container->hasMark($mark),
                        'earned' => $container->hasMark($mark),
                    ];
                }, $component->getMarks())
            ];
        }, $gradeable->getComponents());

        $uploaded_pdfs = [];
        foreach($uploaded_files['submissions'] as $file){
          if(array_key_exists('path',$file) && mime_content_type($file['path']) === "application/pdf"){
                $uploaded_pdfs[] = $file;
            }
        }
        foreach($uploaded_files['checkout'] as $file){
          if(array_key_exists('path',$file) && mime_content_type($file['path']) === "application/pdf"){
                $uploaded_pdfs[] = $file;
            }
        }
        return $this->core->getOutput()->renderTwigTemplate('autograding/TAResultsNew.twig', [
            'been_ta_graded' => $ta_graded_gradeable->isComplete(),
            'ta_graded_version' => $version_instance !== null ? $version_instance->getVersion() : 'INCONSISTENT',
            'any_late_days_used' => $version_instance !== null ? $version_instance->getDaysLate() > 0 : false,
            'overall_comment' => $ta_graded_gradeable->getOverallComment(),
            'is_peer' => $gradeable->isPeerGrading(),
            'components' => $component_data,
            'regrade_date' => $regrade_date,
            'late_days_url' => $late_days_url,
            'grader_names' => $grader_names,
            'grading_complete' => $grading_complete,
            'has_autograding' => $has_autograding,
            'graded_score' => $graded_score,
            'graded_max' => $graded_max,
            'total_score' => $total_score,
            'total_max' => $total_max,
            'active_same_as_graded' => $active_same_as_graded,
            'regrade_available' => $regrade_available,
            'regrade_message' => $regrade_message,
            'num_decimals' => $num_decimals,
            'uploaded_pdfs' => $uploaded_pdfs,
            'gradeable_id' => $gradeable->getId()
        ]);
    }
}
