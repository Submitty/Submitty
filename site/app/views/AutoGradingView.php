<?php

namespace app\views;

use app\models\Gradeable;
use app\views\AbstractView;
use app\libraries\FileUtils;

class AutogradingView extends AbstractView {
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


    public static function loadAutoChecks(Gradeable $gradeable, $index, $who_id, $popup_css_file, $show_hidden = false) {
        $gradeable->loadResultDetails();
        $gradeable_id = $gradeable->getId();
        $testcase = $gradeable->getTestcases()[$index];
        $return = "";

        if ($testcase->isHidden() && !$show_hidden) {
            return "";
        }

        $autocheck_cnt = 0;
        $autocheck_len = count($testcase->getAutochecks());
        foreach ($testcase->getAutochecks() as $autocheck) {
            $description = $autocheck->getDescription();
            $diff_viewer = $autocheck->getDiffViewer();
            $file_path = $diff_viewer->getActualFilename();
            if (substr($file_path, strlen($file_path) - 4, 4) == ".pdf" && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
                $url = "http" . (isset($_SERVER['HTTPS']) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $url = preg_replace('/&component.*/', '', $url);
                $file_name = preg_replace('|.*/|', '', $file_path);
                $file_path = urlencode($file_path);
                $return .= '<iframe src=' . $url . '&component=misc&page=display_file&dir=results&file=' . $file_name . '&path=' . $file_path . ' width="95%" height="1200px" style="border: 0"></iframe>';
            } else {
            	if($description != "STDERR.txt" && $description != "Execution Logfile") {
					$return .= <<<HTML
			<a id="show_char_$index" class="btn btn-default" style="float:right;" onclick="changeDiffView('testcase_$index', '$gradeable_id', '$who_id', '$index')">Show empty chars</a>
HTML;
				}
                $return .= <<<HTML
    <div class="box-block">
    <!-- Readded css here so the popups have the css -->
HTML;
                $title = "";
                $return .= <<<HTML
        <div class='diff-element'>
HTML;
                $display_actual = "";
                $actual_name = $diff_viewer->getActualFilename();
                if ($diff_viewer->hasDisplayExpected() || $actual_name != "") {
                    $title = "Student ";
                }
                if ($diff_viewer->hasDisplayActual()) {
                    $display_actual = $diff_viewer->getDisplayActual();
                    $visible = "visible";
                    $tmp_array_string = explode("\n", trim(html_entity_decode(strip_tags($display_actual)), "\xC2\xA0\t"));
                    $less_than_30 = true;
                    $arr_count = count($tmp_array_string);
                    for ($x = 0; $x < $arr_count; $x++) {
                        if (strlen($tmp_array_string[$x]) > 30) {
                            $less_than_30 = false;
                            $x = $arr_count;
                        }
                    }
                    if (substr_count($display_actual, 'line_number') < 10 && $less_than_30) {
                        $visible = "hidden";
                    }
                } else {
                    $visible = "hidden";
                }
                $title .= $description;
                $return .= <<<HTML
             <h4>{$title} <span onclick="openPopUp('{$popup_css_file}', '{$title}', {$index}, {$autocheck_cnt}, 0);"
             style="visibility: {$visible}"> <i class="fa fa-window-restore" style="visibility: {$visible}; cursor: pointer;"></i></span></h4>



            <div id="container_{$index}_{$autocheck_cnt}_0">
HTML;
                foreach ($autocheck->getMessages() as $message) {
                    $type_class = "black-message";
                    if ($message['type'] == "information") $type_class = "blue-message";
                    else if ($message['type'] == "success") $type_class = "green-message";
                    else if ($message['type'] == "failure") $type_class = "red-message";
                    else if ($message['type'] == "warning") $type_class = "yellow-message";
                    $return .= <<<HTML
            <span class="{$type_class}">{$message['message']}</span><br />
HTML;
                }
                $myimage = $diff_viewer->getActualImageFilename();
                if ($myimage != "") {
                    // borrowed from file-display.php
                    $content_type = FileUtils::getContentType($myimage);
                    if (substr($content_type, 0, 5) === "image") {
                        // Read image path, convert to base64 encoding
                        $imageData = base64_encode(file_get_contents($myimage));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $myimagesrc = 'data: ' . mime_content_type($myimage) . ';charset=utf-8;base64,' . $imageData;
                        // insert the sample image data

                        $return .= '<img src="' . $myimagesrc . '" img style="border:2px solid black">';
                    }
                } else if ($diff_viewer->hasDisplayActual()) {
                    $return .= <<<HTML
            <div id=div_{$index}_{$autocheck_cnt}>
            $display_actual
            </div>
HTML;
                }
                $return .= <<<HTML
            </div>
        </div>
HTML;
                $myExpectedimage = $diff_viewer->getExpectedImageFilename();
                if ($myExpectedimage != "") {
                    $return .= <<<HTML
        <div class='diff-element'>
        <h4>Expected {$description}</h4>
HTML;
                    for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                        $return .= <<<HTML
            <br />
HTML;
                    }
                    // borrowed from file-display.php
                    $content_type = FileUtils::getContentType($myExpectedimage);
                    if (substr($content_type, 0, 5) === "image") {
                        // Read image path, convert to base64 encoding
                        $expectedImageData = base64_encode(file_get_contents($myExpectedimage));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $myExpectedimagesrc = 'data: ' . mime_content_type($myExpectedimage) . ';charset=utf-8;base64,' . $expectedImageData;
                        // insert the sample image data
                        $return .= '<img src="' . $myExpectedimagesrc . '" img style="border:2px solid black">';
                    }
                    $return .= <<<HTML
        </div>
HTML;
                } elseif ($diff_viewer->hasDisplayExpected()) {
                    $visible = "visible";
                    $tmp_array_string = explode("\n", trim(html_entity_decode(strip_tags($diff_viewer->getDisplayExpected())), "\xC2\xA0\t"));
                    $less_than_30 = true;
                    $arr_count = count($tmp_array_string);
                    for ($x = 0; $x < $arr_count; $x++) {
                        if (strlen($tmp_array_string[$x]) > 30) {
                            $less_than_30 = false;
                            $x = $arr_count;
                        }
                    }
                    if (substr_count($diff_viewer->getDisplayExpected(), 'line_number') < 10 && $less_than_30) {
                        $visible = "hidden";
                    }
                    $title = "Expected ";
                    $title .= $description;
                    $return .= <<<HTML
        <div class='diff-element'>
            <h4>{$title} <span onclick="openPopUp('{$popup_css_file}', '{$title}', {$index}, {$autocheck_cnt}, 1)" style="visibility: {$visible}"> <i class="fa fa-window-restore" style="visibility: {$visible}; cursor: pointer;"></i></span></h4>
            <div id="container_{$index}_{$autocheck_cnt}_1">
HTML;
                    for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                        $return .= <<<HTML
                <br />
HTML;
                    }
                    $return .= <<<HTML
                    {$diff_viewer->getDisplayExpected()}
            </div>
        </div>
HTML;
                }
                $myDifferenceImage = $diff_viewer->getDifferenceFilename();
                if ($myDifferenceImage != "") {
                    $return .= <<<HTML
        <div class='diff-element'>
                <h4>Difference {$description}</h4>
HTML;
                    for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                        $return .= <<<HTML
            <br />
HTML;
                    }
                    // borrowed from file-display.php
                    $content_type = FileUtils::getContentType($myDifferenceImage);
                    if (substr($content_type, 0, 5) === "image") {
                        // Read image path, convert to base64 encoding
                        $differenceImageData = base64_encode(file_get_contents($myDifferenceImage));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $differenceImagesrc = 'data: ' . mime_content_type($myDifferenceImage) . ';charset=utf-8;base64,' . $differenceImageData;
                        // insert the sample image data
                        $return .= '<img src="' . $differenceImagesrc . '" img style="border:2px solid black">';
                    }
                    $return .= <<<HTML
        </div>
HTML;
                }

                $return .= <<<HTML
    </div>
HTML;
            }
            if (++$autocheck_cnt < $autocheck_len) {
                $return .= <<<HTML
    <div class="clear"></div>
HTML;
            }

            $diff_viewer->destroyViewer();

//         $return .= <<<HTML
//     </div>
// HTML;
        }
        return $return;
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
        foreach ($gradeable->getComponents() as $component) {
            if (!$component->getGrader()) {
                $grading_complete = false;
            }
        }
        $grader_names = array();
        //find all names of instructors who graded part(s) of this assignment that are full access grader_names
        if (!$gradeable->getPeerGrading()) {
            foreach ($gradeable->getComponents() as $component) {
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

        //Clamp full gradeable score to zero
        $total_score = max($total_score, 0);

        return $this->core->getOutput()->renderTwigTemplate("autograding/TAResults.twig", [
            "gradeable" => $gradeable,
            "grader_names" => $grader_names,
            "grading_complete" => $grading_complete,
            "has_autograding" => $has_autograding,
            "graded_score" => $graded_score,
            "graded_max" => $graded_max,
            "total_score" => $total_score,
            "total_max" => $total_max,
        ]);
    }
}
