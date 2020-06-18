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
use app\libraries\NumberUtils;

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
                    if (!$testcase->canView()) {
                        continue;
                    }
                    if ($testcase->getTestcase()->isHidden()) {
                        $any_visible_hidden = true;
                        break;
                    }
                }
            }

            $show_hidden_breakdown = $any_visible_hidden && $show_hidden;
            // &&($version_instance->getNonHiddenNonExtraCredit() + $version_instance->getHiddenNonExtraCredit() > $autograding_config->getTotalNonHiddenNonExtraCredit());
        }
        // testcases should only be visible if autograding is complete
        if (!$incomplete_autograding) {
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
            'display_version' => $version_instance->getVersion(),
            'is_ta_grading' => $gradeable->isTaGrading(),
            'hide_version_and_test_details' => $gradeable->getAutogradingConfig()->getHideVersionAndTestDetails()
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
                    "url" => $this->core->buildCourseUrl(['display_file']) . '?' . http_build_query([
                        "dir" => $public ? "results_public" : "results",
                        "file" => $file_name,
                        "path" => $file_path
                    ])
                ];
            }
            else {
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
                }
                elseif ($diff_viewer->hasDisplayActual()) {
                    if ($autocheck->isDisplayAsSequenceDiagram()) {
                        $check["actual"] = [
                            "type" => "sequence_diagram",
                            "title" => $actual_title,
                            "show_popup" => $this->autoShouldDisplayPopup($actual_display),
                            "src" => file_get_contents($diff_viewer->getActualFilename())
                        ];
                    }
                    else {
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
                }
                elseif ($diff_viewer->hasDisplayExpected()) {
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

        $popup_css_file = $this->core->getOutput()->timestampResource($popup_css_file, 'css');
        return $this->core->getOutput()->renderTwigTemplate("autograding/AutoChecks.twig", [
            "gradeable_id" => $gradeable->getId(),
            "checks" => $checks,
            "display_version" => $version->getVersion(),
            "index" => $testcase->getTestcase()->getIndex(),
            "who" => $who,
            "popup_css_file" => $popup_css_file
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
     * @param TaGradedGradeable $ta_graded_gradeable
     * @param bool $regrade_available
     * @param array $uploaded_files
     * @return string
     */
    public function showTAResults(TaGradedGradeable $ta_graded_gradeable, bool $regrade_available, array $uploaded_files) {
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
        $ta_grader_names = array_map(function (User $grader) {
            return $grader->getDisplayedFirstName() . ' ' . $grader->getDisplayedLastName();
        }, $ta_graded_gradeable->getVisibleGraders());

        if (count($ta_grader_names) === 0) {
            // Non-peer assignment with only limited access graders
            $ta_grader_names = ['Course Staff'];
        }

        //get total score and max possible score
        $ta_max = $gradeable->getTaPoints();

        // Get the number of decimal places for floats to display nicely
        $num_decimals = 0;
        $precision_parts = explode('.', strval($gradeable->getPrecision()));
        if (count($precision_parts) > 1) {
            // TODO: this hardcoded value will mean a weird precision value (like 1/3) won't appear ridiculous
            $num_decimals = min(3, count($precision_parts));
        }

        // Get just the non-peer components.
        $ta_graded_components = array_filter($gradeable->getComponents(), function (Component $component) {
            return $component->isPeer() === false;
        });

        $ta_grading_earned = 0;
        $ta_component_data = array_map(function (Component $component) use ($ta_graded_gradeable, &$ta_grading_earned) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            $component_marks = array_map(function (Mark $mark) use ($container) {
                return [
                    'title' => $mark->getTitle(),
                    'points' => $mark->getPoints(),

                    'show_mark' => $mark->isPublish() || $container->hasMark($mark),
                    'earned' => $container->hasMark($mark)
                ];
            }, $component->getMarks());
            $ta_grading_earned += $container->getTotalScore();
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
                'marks' => $component_marks,
            ];
        }, $ta_graded_components);

        $uploaded_pdfs = [];
        foreach ($uploaded_files['submissions'] as $file) {
            if (array_key_exists('path', $file) && mime_content_type($file['path']) === "application/pdf") {
                $uploaded_pdfs[] = $file;
            }
        }
        foreach ($uploaded_files['checkout'] as $file) {
            if (array_key_exists('path', $file) && mime_content_type($file['path']) === "application/pdf") {
                $uploaded_pdfs[] = $file;
            }
        }

        $files = null;
        $display_version = 0;
        if ($version_instance !== null) {
            $files = $version_instance->getFiles();
            $display_version = $version_instance->getVersion();
            // for bulk uploads only show PDFs
            if ($gradeable->isScannedExam()) {
                $files = $uploaded_pdfs;
            }
            else {
                $files = array_merge($files['submissions'], $files['checkout']);
            }
        }

        $id = $this->core->getUser()->getId();
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $id)->getId();
        }

        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable->getId(), $id, $active_version);
        $annotated_file_names = [];
        if (is_dir($annotation_path) && count(scandir($annotation_path)) > 2) {
            $first_file = scandir($annotation_path)[2];
            $annotation_path = FileUtils::joinPaths($annotation_path, $first_file);
            if (is_file($annotation_path)) {
                $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
                foreach ($dir_iter as $fileinfo) {
                    if (!$fileinfo->isDot()) {
                        $no_extension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                        $pdf_info = explode('_', $no_extension);
                        $pdf_id = implode("_", array_slice($pdf_info, 0, -1));
                        if (file_get_contents($fileinfo->getPathname()) != "") {
                            $pdf_id = $pdf_id . '.pdf';
                            $annotated_file_names[] = $pdf_id;
                        }
                    }
                }
            }
        }

        // Update overall comments to have display names
        $overall_comments = [];
        foreach ($ta_graded_gradeable->getOverallComments() as $user_name => $comment) {
            $comment_user = $this->core->getQueries()->getUserById($user_name);
            $display_name = $comment_user->getDisplayedFirstName();

            // Skip peers.
            if ($gradeable->isPeerGrading() && !$comment_user->accessGrading()) {
                continue;
            }

            // Skip empty comments
            if (strlen(trim($comment)) > 0) {
                $overall_comments[$display_name] = $comment;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate('autograding/TAResults.twig', [
            'files' => $files,
            'been_ta_graded' => $ta_graded_gradeable->isComplete(),
            'ta_graded_version' => $version_instance !== null ? $version_instance->getVersion() : 'INCONSISTENT',
            'overall_comments' => $overall_comments,
            'ta_components' => $ta_component_data,
            'regrade_date' => DateUtils::dateTimeToString($gradeable->getRegradeRequestDate()),
            'grading_complete' => $grading_complete,
            'ta_score' => $ta_grading_earned,
            'ta_max' => $ta_max,
            'active_same_as_graded' => $active_same_as_graded,
            'regrade_available' => $regrade_available,
            'regrade_message' => $this->core->getConfig()->getRegradeMessage(),
            'num_decimals' => $num_decimals,
            'uploaded_pdfs' => $uploaded_pdfs,
            'user_id' => $this->core->getUser()->getId(),
            'gradeable_id' => $gradeable->getId(),
            'can_download' => !$gradeable->isVcs(),
            'display_version' => $display_version,
            'display_original_pdf' => $this->core->buildCourseUrl(['display_file']),
            'student_pdf_view_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'pdf']),
            "annotated_file_names" =>  $annotated_file_names
        ]);
    }


    /**
     * @param TaGradedGradeable $ta_graded_gradeable
     * @param bool $regrade_available
     * @param array $uploaded_files
     * @return string
     */
    public function showPeerResults(TaGradedGradeable $ta_graded_gradeable, bool $regrade_available, array $uploaded_files) {
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

        //get total score and max possible score
        $peer_max = $gradeable->getPeerPoints();

        // Get the number of decimal places for floats to display nicely
        $num_decimals = 0;
        $precision_parts = explode('.', strval($gradeable->getPrecision()));
        if (count($precision_parts) > 1) {
            // TODO: this hardcoded value will mean a weird precision value (like 1/3) won't appear ridiculous
            $num_decimals = min(3, count($precision_parts));
        }

        $graders_found = [];
        $peer_aliases = [];
        $peer_grading_earned = 0;

        // Get just the peer components.
        $peer_graded_components = array_filter($gradeable->getComponents(), function (Component $component) {
            return $component->isPeer() === true;
        });

        $peer_component_data = array_map(function (Component $component) use ($ta_graded_gradeable, &$graders_found, &$peer_grading_earned) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            $component_marks = array_map(function (Mark $mark) use ($container) {
                return [
                    'title' => $mark->getTitle(),
                    'points' => $mark->getPoints(),

                    'show_mark' => $mark->isPublish() || $container->hasMark($mark),
                    'earned' => array_map(function (User $grader) use ($mark, $container) {
                                    return $container->hasMark($mark, $grader);
                    }, $container->getGraders()),
                    'num_earned' => count(array_filter($container->getGraders(), function (User $grader) use ($mark, $container) {
                                    return $container->hasMark($mark, $grader);
                    })),
                ];
            }, $component->getMarks());

            $peer_grading_earned += $container->getTotalScore();
            // Maintain names (Peer 1, Peer 2, etc.) for each of the peer graders to help the student
            // reason about feedback.
            foreach ($container->getGraders() as $grader) {
                array_push($graders_found, $grader->getId());
            }
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
                'peer_ids' => array_values(array_map(function (User $grader) {
                    return $grader->getId();
                }, $container->getGraders())),
                'marks' => $component_marks,
            ];
        }, $peer_graded_components);

        $unique_graders = array_unique($graders_found);
        $peer_aliases = [];
        $num_peers = 0;
        // Sort the graders ids so that peer alias assignment stays mostly consistent.
        // TODO: Eventually we want to move to having a students anonid be displayable
        // So that we don't have to do this (e.g. Anonymous Moose).
        sort($unique_graders);
        $ordered_graders = [];
        foreach ($unique_graders as $grader_id) {
            $num_peers += 1;
            $alias = "Peer " . $num_peers;
            $peer_aliases[$grader_id] = $alias;
            // Effectively sorts peers by $num_peers.
            array_push($ordered_graders, $grader_id);
        }

        $uploaded_pdfs = [];
        foreach ($uploaded_files['submissions'] as $file) {
            if (array_key_exists('path', $file) && mime_content_type($file['path']) === "application/pdf") {
                $uploaded_pdfs[] = $file;
            }
        }
        foreach ($uploaded_files['checkout'] as $file) {
            if (array_key_exists('path', $file) && mime_content_type($file['path']) === "application/pdf") {
                $uploaded_pdfs[] = $file;
            }
        }

        $files = null;
        $display_version = 0;
        if ($version_instance !== null) {
            $files = $version_instance->getFiles();
            $display_version = $version_instance->getVersion();

            // for bulk uploads only show PDFs
            if ($gradeable->isScannedExam()) {
                $files = $uploaded_pdfs;
            }
            else {
                $files = array_merge($files['submissions'], $files['checkout']);
            }
        }

        $id = $this->core->getUser()->getId();
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $id)->getId();
        }

        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable->getId(), $id, $active_version);
        $annotated_file_names = [];
        if (is_dir($annotation_path) && count(scandir($annotation_path)) > 2) {
            $first_file = scandir($annotation_path)[2];
            $annotation_path = FileUtils::joinPaths($annotation_path, $first_file);
            if (is_file($annotation_path)) {
                $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
                foreach ($dir_iter as $fileinfo) {
                    if (!$fileinfo->isDot()) {
                        $no_extension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                        $pdf_info = explode('_', $no_extension);
                        $pdf_id = implode("_", array_slice($pdf_info, 0, -1));
                        if (file_get_contents($fileinfo->getPathname()) != "") {
                            $pdf_id = $pdf_id . '.pdf';
                            $annotated_file_names[] = $pdf_id;
                        }
                    }
                }
            }
        }
        $overall_comments = [];
        foreach ($ta_graded_gradeable->getOverallComments() as $user_id => $comment) {
            $comment_user = $this->core->getQueries()->getUserById($user_id);

            // Skip non-peers.
            if ($gradeable->isPeerGrading() && $comment_user->accessGrading()) {
                continue;
            }

            // Skip empty comments
            if (strlen(trim($comment)) > 0) {
                $overall_comments[$user_id] = $comment;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate('autograding/PeerResults.twig', [
            'files' => $files,
            'been_ta_graded' => $ta_graded_gradeable->isComplete(),
            'ta_graded_version' => $version_instance !== null ? $version_instance->getVersion() : 'INCONSISTENT',
            'overall_comments' => $overall_comments,
            'is_peer' => $gradeable->isPeerGrading(),
            'peer_components' => $peer_component_data,
            'peer_aliases' => $peer_aliases,
            'ordered_graders' => $ordered_graders,
            'regrade_date' => DateUtils::dateTimeToString($gradeable->getRegradeRequestDate()),
            'grading_complete' => $grading_complete,
            'peer_score' => $peer_grading_earned,
            'peer_max' => $peer_max,
            'active_same_as_graded' => $active_same_as_graded,
            'regrade_available' => $regrade_available,
            'regrade_message' => $this->core->getConfig()->getRegradeMessage(),
            'num_decimals' => $num_decimals,
            'uploaded_pdfs' => $uploaded_pdfs,
            'user_id' => $this->core->getUser()->getId(),
            'gradeable_id' => $gradeable->getId(),
            'can_download' => !$gradeable->isVcs(),
            'display_version' => $display_version,
            'student_pdf_view_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'pdf']),
            "annotated_file_names" =>  $annotated_file_names
        ]);
    }
}
