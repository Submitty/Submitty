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

class AutoGradingView extends AbstractView {

    /**
     * @param Gradeable $gradeable
     * @param bool $show_hidden
     * @return string
     */
    public function showResults(Gradeable $gradeable, $show_hidden = false) {
        $current_version = $gradeable->getCurrentVersion();
        $has_badges = false;
        $num_visible_testcases = 0;

        $nonhidden_earned = 0;
        $nonhidden_max = 0;
        $hidden_earned = 0;
        $hidden_max = 0;
        $show_hidden_breakdown = false;
        $display_hidden = false;

        foreach ($gradeable->getTestcases() as $testcase) {
            if ($testcase->viewTestcase()) {
                $num_visible_testcases++;
            }
        }

        if ($current_version->getNonHiddenTotal() >= 0) {
            $has_badges = true;

            $nonhidden_earned = $current_version->getNonHiddenTotal();
            $nonhidden_max = $gradeable->getNormalPoints();
            $hidden_earned = $current_version->getNonHiddenTotal() + $current_version->getHiddenTotal();
            $hidden_max = $gradeable->getTotalAutograderNonExtraCreditPoints();

            $show_hidden_breakdown = ($current_version->getNonHiddenNonExtraCredit() + $current_version->getHiddenNonExtraCredit() > $gradeable->getNormalPoints()) && $show_hidden;

            $display_hidden = false;
            if ($gradeable->taGradesReleased()) {
                foreach ($gradeable->getTestcases() as $testcase) {
                    if (!$testcase->viewTestcase()) continue;
                    if ($testcase->isHidden()) {
                        $display_hidden = true;
                        break;
                    }
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("autograding/AutoResults.twig", [
            "gradeable" => $gradeable,
            "show_hidden" => $show_hidden,
            "num_visible_testcases" => $num_visible_testcases,
            "show_hidden_breakdown" => $show_hidden_breakdown,
            "display_hidden" => $display_hidden,
            "nonhidden_earned" => $nonhidden_earned,
            "nonhidden_max" => $nonhidden_max,
            "hidden_earned" => $hidden_earned,
            "hidden_max" => $hidden_max,
            "has_badges" => $has_badges,
        ]);
    }

    /**
     * @param AutoGradedVersion $version_instance
     * @param bool $show_hidden
     * @return string
     */
    public function showResultsNew(AutoGradedVersion $version_instance, bool $show_hidden = false) {
        $graded_gradeable = $version_instance->getGradedGradeable();
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();

        $has_badges = false;
        $nonhidden_earned = 0;
        $nonhidden_max = 0;
        $hidden_earned = 0;
        $hidden_max = 0;
        $show_hidden_breakdown = false;
        $display_hidden = false;
        $num_visible_testcases = 0;

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

            $show_hidden_breakdown = ($version_instance->getNonHiddenNonExtraCredit() + $version_instance->getHiddenNonExtraCredit() > $autograding_config->getTotalNonHiddenNonExtraCredit()) && $show_hidden;

            $display_hidden = false;
            if ($gradeable->isTaGradeReleased()) {
                foreach ($version_instance->getTestcases() as $testcase) {
                    if (!$testcase->canView()) continue;
                    if ($testcase->getTestcase()->isHidden()) {
                        $display_hidden = true;
                        break;
                    }
                }
            }
        }
        foreach ($version_instance->getTestcases() as $testcase) {
            if ($testcase->canView()) {
                $num_visible_testcases++;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("autograding/AutoResultsNew.twig", [
            'gradeable_id' => $gradeable->getId(),
            'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            "num_visible_testcases" => $num_visible_testcases,
            "show_hidden_breakdown" => $show_hidden_breakdown,
            "nonhidden_earned" => $nonhidden_earned,
            "nonhidden_max" => $nonhidden_max,
            "hidden_earned" => $hidden_earned,
            "hidden_max" => $hidden_max,
            "display_hidden" => $display_hidden,
            "has_badges" => $has_badges,
            'testcases' => $testcase_array,
            'is_ta_grading_complete' => $graded_gradeable->isTaGradingComplete(),
            "show_hidden" => $show_hidden,
            'display_version' => $version_instance->getVersion()
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param $index
     * @param $popup_css_file
     * @param string $who
     * @param bool $show_hidden
     * @return string
     * @throws \Exception
     */
    public function loadAutoChecks(Gradeable $gradeable, $index, $popup_css_file, $who, $show_hidden = false) {
        $gradeable->loadResultDetails();
        $testcase = $gradeable->getTestcases()[$index];

        if ($testcase->isHidden() && !$show_hidden) {
            return "";
        }

        $checks = [];

        foreach ($testcase->getAutochecks() as $autocheck) {
            $description = $autocheck->getDescription();
            $diff_viewer = $autocheck->getDiffViewer();
            $file_path = $diff_viewer->getActualFilename();
            if (substr($file_path, strlen($file_path) - 4, 4) == ".pdf") {
                $file_name = pathinfo($file_path, PATHINFO_BASENAME);
                $file_path = urlencode($file_path);
                $checks[] = [
                    "pdf" => true,
                    "name" => $file_name,
                    "path" => $file_path
                ];
            } else {
                $check = [
                    "messages" => $autocheck->getMessages()
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
                    $check["actual"] = [
                        "type" => "text",
                        "title" => $actual_title,
                        "show_popup" => $this->autoShouldDisplayPopup($actual_display),
                        "src" => $actual_display,
                    ];
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
            "gradeable" => $gradeable,
            "checks" => $checks,
            "index" => $index,
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

    public function showVersionChoice($gradeable, $onChange, $formatting = "") {
        return $this->core->getOutput()->renderTwigTemplate("grading/VersionChoice.twig", [
            "gradeable" => $gradeable,
            "onChange" => $onChange,
            "formatting" => $formatting,
        ]);
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
                if ($component->getGrader() == NULL) {
                    continue;
                }
                $name = $component->getGrader()->getDisplayedFirstName() . " " . $component->getGrader()->getLastName();
                if (!in_array($name, $grader_names) && $component->getGrader()->accessFullGrading()) {
                    $grader_names[] = $name;
                }
            }
        } else {
            $grader_names = "Graded by Peer(s)";
        }
        //get total score and max possible score
        $graded_score = $gradeable->getGradedTAPoints();
        $graded_max = $gradeable->getTotalTANonExtraCreditPoints();
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
        $current = $gradeable->getCurrentVersion() == NULL ? $gradeable->getVersions()[1] : $gradeable->getCurrentVersion();
        $total_score = $current->getNonHiddenTotal() + $current->getHiddenTotal() + $graded_score;
        $total_max = $gradeable->getTotalAutograderNonExtraCreditPoints() + $graded_max;
        $regrade_enabled = $this->core->getConfig()->isRegradeEnabled();
        $regrade_message = $this->core->getConfig()->getRegradeMessage();
        //Clamp full gradeable score to zero
        $total_score = max($total_score, 0);

        $num_decimals = strlen(substr(strrchr((string)$gradeable->getPointPrecision(), "."), 1));

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
            "regrade_enabled" => $regrade_enabled,
            "regrade_message" => $regrade_message,
            "num_decimals" => $num_decimals
        ]);
    }

    /**
     * @param TaGradedGradeable $ta_graded_gradeable
     * @param bool $regrade_available
     * @return string
     */
    public function showTAResultsNew(TaGradedGradeable $ta_graded_gradeable, bool $regrade_available) {
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
            return $grader->getDisplayedFirstName() . ' ' . $grader->getLastName();
        }, $ta_graded_gradeable->getVisibleGraders());

        // Special messages for peer / mentor-only grades
        if ($gradeable->isPeerGrading()) {
            $grader_names = ['Graded by Peer(s)'];
        } else if (count($grader_names) === 0) {
            // Non-peer assignment with only limited access graders
            $grader_names = ['Graded by the ghosts among you'];
        }

        //get total score and max possible score
        $total_score = $graded_score = $ta_graded_gradeable->getTotalScore();
        $total_max = $graded_max = $gradeable->getTaNonExtraCreditPoints();

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

        // Get the number of decimal places for floats to display nicely
        $num_decimals = 0;
        $precision_parts = explode('.', strval($gradeable->getPrecision()));
        if (count($precision_parts) > 1) {
            // TODO: this hardcoded value will mean a weird precision value (like 1/3) won't appear ridiculous
            $num_decimals = min(3, count($precision_parts[1]));
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
                    return $grader->getLastName();
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

        return $this->core->getOutput()->renderTwigTemplate('autograding/TAResultsNew.twig', [
            'been_ta_graded' => $ta_graded_gradeable->isComplete(),
            'ta_graded_version' => $version_instance !== null ? $version_instance->getVersion() : 'INCONSISTENT',
            'any_late_days_used' => $version_instance !== null ? $version_instance->getDaysLate() > 0 : false,
            'overall_comment' => $ta_graded_gradeable->getOverallComment(),
            'is_peer' => $gradeable->isPeerGrading(),
            'components' => $component_data,

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
            'num_decimals' => $num_decimals
        ]);
    }
}
