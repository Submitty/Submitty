<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\routers\AccessControl;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\SimpleStat;
use app\models\GradingOrder;
use app\models\User;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class GradingStatusController extends AbstractController {
    /**
     * Generates histogram data needed for the TA stats page
     * @param GradedGradeable[] $overall_scores
     * @return array of histogram data
     */
    public function generateHistogramData($overall_scores) {
        $include_grade_override  = ($_COOKIE['include_grade_override'] ?? 'omit') === 'include';
        $include_bad_submissions = ($_COOKIE['include_bad_submissions'] ?? 'omit') === 'include';
        $include_null_section    = ($_COOKIE['include_null_section'] ?? 'omit') === 'include';
        $include_withdrawn       = ($_COOKIE['include_withdrawn_students'] ?? 'omit') === 'include';

        $overridden_user_ids = [];
        if (!$include_grade_override && count($overall_scores) > 0) {
            $g_id = $overall_scores[0]->getGradeable()->getId();
            $rows = $this->core->getQueries()->getRawUsersWithOverriddenGrades($g_id);
            foreach ($rows as $r) {
                $overridden_user_ids[$r['user_id']] = true;
            }
        }

        $histogram = [
            "bTA" => [], "tTA" => [], "bAuto" => [],
            "runtime" => [], "memory" => [], "submitters" => [],
            "VerConf" => 0, "noSub" => 0, "noActive" => 0,
            "GradeInq" => 0, "IncompGrading" => 0, "cancelledSub" => 0
        ];

        foreach ($overall_scores as $ov) {
            $submitter = null;
            if ($ov->getTaGradedGradeable() !== null) {
                $submitter = $ov->getTaGradedGradeable()->getGradedGradeable()->getSubmitter();
            }
            else {
                $submitter = $ov->getAutoGradedGradeable()->getGradedGradeable()->getSubmitter();
            }

            $reg_section = $submitter->getRegistrationSection();
            if (!$include_null_section && $reg_section === null) {
                continue;
            }
            if (!$include_withdrawn && !$ov->getGradeable()->isTeamAssignment() && $submitter->getUser()->getRegistrationType() === "withdrawn") {
                continue;
            }

            $submitter_id = $submitter->getId();
            if (!$include_grade_override && isset($overridden_user_ids[$submitter_id])) {
                continue;
            }

            $is_bad_submission = false;
            $auto = $ov->getAutoGradedGradeable();
            if ($auto !== null) {
                $graded_gradeable = $auto->getGradedGradeable();
                $user = $submitter->getUser();
                if ($user !== null && $graded_gradeable !== null) {
                    $ldi = $this->core->getQueries()->getLateDayInfoForUserGradeable($user, $graded_gradeable);
                    if ($ldi !== null) {
                        $is_bad_submission = ($ldi->getStatus() === LateDayInfo::STATUS_BAD);
                    }
                }
            }

            if (!$include_bad_submissions && $is_bad_submission) {
                continue;
            }

            if ($ov->getAutoGradedGradeable()->getHighestVersion() !== 0) {
                if ($ov->getAutoGradedGradeable()->getActiveVersion() !== 0) {
                    if (!$ov->getOrCreateTaGradedGradeable()->hasVersionConflict()) {
                        $histogram["bAuto"] = array_merge($histogram["bAuto"], [$ov->getAutoGradedGradeable()->getTotalPoints()]);
                        $metrics = $ov->getAutoGradedGradeable()->getMetrics_Sum();
                        $histogram["runtime"] = array_merge($histogram["runtime"], [$metrics['runtime']]);
                        $histogram["memory"]  = array_merge($histogram["memory"], [$metrics['memory']]);
                        $histogram["submitters"] = array_merge($histogram["submitters"], [$ov->getAutoGradedGradeable()->getSubmitterId()]);
                    }
                }
            }

            if (!$ov->getAutoGradedGradeable()->hasSubmission()) {
                $histogram["noSub"] += 1;
            }
            elseif ($ov->getAutoGradedGradeable()->getHighestVersion() !== 0 && $ov->getAutoGradedGradeable()->getActiveVersion() === 0) {
                $histogram["cancelledSub"] += 1;
            }
            elseif ($ov->getAutoGradedGradeable()->getActiveVersion() === 0) {
                $histogram["noActive"] += 1;
            }
            elseif ($ov->getTaGradedGradeable()->anyGrades()) {
                if ($ov->hasActiveGradeInquiry()) {
                    $histogram["GradeInq"] += 1;
                }
                elseif ($ov->getTaGradedGradeable()->hasVersionConflict()) {
                    $histogram["VerConf"] += 1;
                }
                elseif (!$ov->isTaGradingComplete()) {
                    $histogram["IncompGrading"] += 1;
                }
                else {
                    $histogram["bTA"] = array_merge($histogram["bTA"], [$ov->getTaGradedGradeable()->getTotalScore() + $ov->getAutoGradedGradeable()->getTotalPoints()]);
                    $histogram["tTA"] = array_merge($histogram["tTA"], [$ov->getTaGradedGradeable()->getTotalScore()]);
                }
            }
        }
        return $histogram;
    }

    /**
     * Shows statistics for the grading status of a given electronic submission.
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/status")]
    public function showStatus($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid gradeable id');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->hasAutogradingConfig()) {
            $this->core->getOutput()->renderOutput('Error', 'unbuiltGradeable', $gradeable, "grades");
            return;
        }

        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$this->core->getAccess()->canI("grading.electronic.status", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getTitle()}");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getTitle()} Grading", $gradeableUrl);
        $this->core->getOutput()->addBreadcrumb("Statistics & Charts");

        $isPeerGradeable = false;
        if ($gradeable->hasPeerComponent() && ($this->core->getUser()->getGroup() < User::GROUP_STUDENT)) {
            $isPeerGradeable = true;
        }
        $peer = false;
        if ($gradeable->hasPeerComponent() && ($this->core->getUser()->getGroup() == User::GROUP_STUDENT)) {
            $peer = true;
        }

        $submissions_in_queue = 0;
        $gradeables[] = $gradeable;
        $graded_gradeables = $this->core->getQueries()->getGradedGradeables($gradeables);
        foreach ($graded_gradeables as $g) {
            $highest_version = $g->getAutoGradedGradeable()->getHighestVersion();
            if ($highest_version > 0) {
                for ($i = 1; $i < $highest_version + 1; $i++) {
                    $display_version_instance = $g->getAutoGradedGradeable()->getAutoGradedVersionInstance($i);
                    if ($display_version_instance->isQueued()) {
                        $submissions_in_queue += 1;
                    }
                }
            }
        }

        $this->buildStatusData($gradeable, $gradeable_id, $isPeerGradeable, $peer, $submissions_in_queue);
    }

    /**
     * Build all the data for the status page and render
     */
    private function buildStatusData($gradeable, $gradeable_id, $isPeerGradeable, $peer, $submissions_in_queue) {
        $team_users = [];
        $no_team_users = [];
        $my_grading = 0;
        $num_components = 0;
        $graded_components = [];
        $late_components = [];
        $ta_graded_components = [];
        $graders = [];
        $sections = [];
        $total_users = [];
        $component_averages = [];
        $histogram_data = [];
        $manual_average = null;
        $autograded_average = null;
        $overall_average = null;
        $overall_scores = null;
        $order = null;
        $num_submitted = [];
        $viewed_grade = 0;
        $num_gradeables = 1;
        $total_who_submitted = 0;
        $peers_to_grade = 0;
        $peer_graded_components = 0;
        $peer_components = 0;
        $total_users_who_submitted = [];
        $graders_of_inquiries = [];
        $verified_components = [];

        $include_withdrawn_students = ($_COOKIE['include_withdrawn_students'] ?? 'omit') === 'include';

        $this->core->getQueries()->generateLateDayCacheForUsers();
        $section_key = ($gradeable->isGradeByRegistration() ? 'registration_section' : 'rotating_section');
        $grade_inquiries = $this->core->getQueries()->getNumberGradeInquiries($gradeable_id, $gradeable->isGradeInquiryPerComponentAllowed());
        $graders_of_inquiries = $this->core->getQueries()->getGraderofGradeInquiry($gradeable_id, $gradeable->isGradeInquiryPerComponentAllowed());

        if ($isPeerGradeable) {
            $total_users_who_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key, $include_withdrawn_students);
            $peer_graded_components = 0;
            $order = new GradingOrder($this->core, $gradeable, $this->core->getUser(), true);
            $student_array = [];
            $student_list = [];
            $students = $this->core->getQueries()->getUsersByRegistrationSections($order->getSectionNames());
            foreach ($students as $student) {
                $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
                $sorted_students[$reg_sec][] = $student;
                array_push($student_list, ['user_id' => $student->getId()]);
                array_push($student_array, $student->getId());
            }
            foreach ($student_array as $student) {
                $peer_graded_components += $this->core->getQueries()->getNumGradedPeerComponents($gradeable_id, $student);
                $peer_components += count($this->core->getQueries()->getPeerAssignment($gradeable_id, $student));
            }
        }

        if ($peer) {
            $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key, $include_withdrawn_students);
            $peer_array = $this->core->getQueries()->getPeerAssignment($gradeable_id, $this->core->getUser()->getId());
            $peers_to_grade = count($peer_array);
            $num_components = count($gradeable->getPeerComponents());
            $graded_components = $this->core->getQueries()->getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections);
            $late_components = $this->core->getQueries()->getBadTeamSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
            $ta_graded_components = $this->core->getQueries()->getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections);
            $num_gradeables = count($this->core->getQueries()->getPeerGradingAssignmentsForGrader($this->core->getUser()->getId()));
            $my_grading = $this->core->getQueries()->getNumGradedPeerComponents($gradeable_id, $this->core->getUser()->getId());
            $component_averages = [];
            $manual_average = null;
            $autograded_average = null;
            $overall_average = null;
            $overall_scores = null;
        }
        elseif ($gradeable->isGradeByRegistration()) {
            if (!$this->core->getAccess()->canI("grading.electronic.status.full")) {
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
            $num_components = count($gradeable->getNonPeerComponents());
        }
        else {
            if (!$this->core->getAccess()->canI("grading.electronic.status.full")) {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }

        if ($gradeable->isTeamAssignment()) {
            $num_submitted = $this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, $section_key);
            $late_submitted = $this->core->getQueries()->getBadTeamSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
        }
        else {
            $num_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key, $include_withdrawn_students);
            $late_submitted = $this->core->getQueries()->getBadUserSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
        }

        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $team_users = $this->core->getQueries()->getUsersWithTeamByGradingSections($gradeable_id, $sections, $section_key);
                $individual_viewed_grade = $this->core->getQueries()->getNumUsersWhoViewedTeamAssignmentBySection($gradeable, $sections);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key, $include_withdrawn_students);
                $no_team_users = [];
                $team_users = [];
                $individual_viewed_grade = 0;
            }
            $override_cookie = $_COOKIE['include_grade_override'] ?? 'omit';
            $bad_submissions_cookie = $_COOKIE['include_bad_submissions'] ?? 'omit';
            $null_section_cookie = $_COOKIE['include_null_section'] ?? 'omit';
            $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);
            $late_components = $this->core->getQueries()->getBadGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);
            $component_averages = $this->core->getQueries()->getAverageComponentScores($gradeable_id, $section_key, $gradeable->isTeamAssignment(), $bad_submissions_cookie, $null_section_cookie, $include_withdrawn_students);
            $autograded_average = $this->core->getQueries()->getAverageAutogradedScores($gradeable_id, $section_key, $gradeable->isTeamAssignment(), $bad_submissions_cookie, $null_section_cookie, $include_withdrawn_students);
            $overall_average = $this->core->getQueries()->getAverageForGradeable($gradeable_id, $section_key, $gradeable->isTeamAssignment(), $override_cookie, $bad_submissions_cookie, $null_section_cookie, $include_withdrawn_students);
            $manual_average = new SimpleStat($this->core, [
                'avg_score' => $overall_average['manual_avg_score'],
                'std_dev' => $overall_average['manual_std_dev'],
                'max' => $overall_average['manual_max_score'],
                'count' => $overall_average['count']
            ]);
            $overall_average = new SimpleStat($this->core, $overall_average);
            $order = new GradingOrder($this->core, $gradeable, $this->core->getUser(), true);
            $overall_scores = $order->getSortedGradedGradeables();
            $num_components = count($gradeable->getNonPeerComponents());
            $viewed_grade = $this->core->getQueries()->getNumUsersWhoViewedGradeBySections($gradeable, $sections, $null_section_cookie);
            $histogram_data = $this->generateHistogramData($overall_scores);
            $verified_components = $this->core->getQueries()->getVerifiedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment());
        }

        $sections = $this->buildSections(
            $gradeable, $total_users, $total_users_who_submitted, $num_submitted, $late_submitted ?? [],
            $graded_components, $late_components, $verified_components, $graders,
            $num_components, $my_grading, $peer, $isPeerGradeable,
            $peer_graded_components, $peer_components, $num_gradeables,
            $peers_to_grade, $total_who_submitted, $no_team_users, $team_users
        );

        $total_submissions = 0;
        if (count($total_users) > 0) {
            foreach ($total_users as $key => $value) {
                if ($key === "NULL" && ($_COOKIE['include_null_section'] ?? 'omit') === 'omit') {
                    continue;
                }
                $total_submissions += $value;
            }
        }

        $registered_but_not_rotating = count($this->core->getQueries()->getRegisteredUsersWithNoRotatingSection());
        $rotating_but_not_registered = count($this->core->getQueries()->getUnregisteredStudentsWithRotatingSection());
        $show_warnings = $this->core->getAccess()->canI("grading.electronic.status.warnings");

        if (isset($order) && $gradeable->isTeamAssignment()) {
            $total_students_submitted = 0;
            foreach ($order->getSortedGradedGradeables() as $g) {
                $team = $g->getSubmitter()->getTeam();
                $team_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if (array_key_exists($team_section, $total_users)) {
                    if ($this->core->getQueries()->getActiveVersionForTeam($gradeable->getId(), $team->getId()) != 0) {
                        $total_students_submitted += count($team->getMembers());
                    }
                }
            }
        }
        else {
            $total_students_submitted = 0;
        }

        $this->core->getOutput()->renderOutput(
            ['grading', 'ElectronicGrader'], 'statusPage',
            $gradeable, $sections, $component_averages, $manual_average,
            $autograded_average, $overall_scores, $overall_average,
            $histogram_data, $total_submissions, $individual_viewed_grade ?? 0,
            $total_students_submitted, $registered_but_not_rotating,
            $rotating_but_not_registered, $viewed_grade, $section_key,
            $grade_inquiries, $graders_of_inquiries, $show_warnings, $submissions_in_queue
        );
    }

    /**
     * Build the sections array for the status page
     */
    private function buildSections(
        $gradeable, $total_users, $total_users_who_submitted, $num_submitted, $late_submitted,
        $graded_components, $late_components, $verified_components, $graders,
        $num_components, $my_grading, $peer, $isPeerGradeable,
        $peer_graded_components, $peer_components, $num_gradeables,
        $peers_to_grade, &$total_who_submitted, $no_team_users, $team_users
    ) {
        $sections = [];
        if (count($total_users) === 0) {
            return $sections;
        }

        foreach ($total_users_who_submitted as $key => $value) {
            if ($key === "NULL" && ($_COOKIE['include_null_section'] ?? 'omit') === 'omit') {
                continue;
            }
            $total_who_submitted += $value;
        }

        if (!$gradeable->isTeamAssignment() && $isPeerGradeable) {
            $sections['peer_stu_grad'] = [
                'total_who_submitted' => $total_who_submitted,
                'total_components' => count($gradeable->getPeerComponents()) * $total_who_submitted,
                'non_late_total_components' => count($gradeable->getPeerComponents()) * $total_who_submitted,
                'graded_components' => 0, 'verified_components' => 0,
                'non_late_graded_components' => 0, 'non_late_verified_components' => 0,
                'view_peer_graded_components' => $peer_graded_components,
                'view_peer_components' => $peer_components,
                'ta_graded_components' => 0, 'num_gradeables' => $num_gradeables,
                'graders' => [], 'valid_graders' => []
            ];
        }

        if ($peer) {
            if ($gradeable->isTeamAssignment()) {
                $sections['stu_grad'] = [
                    'total_components' => count($gradeable->getPeerComponents()),
                    'non_late_total_components' => count($gradeable->getPeerComponents()),
                    'graded_components' => $my_grading, 'verified_components' => $my_grading,
                    'non_late_graded_components' => $my_grading, 'non_late_verified_components' => $my_grading,
                    'num_gradeables' => $num_gradeables, 'ta_graded_components' => 0,
                    'graders' => [], 'valid_graders' => []
                ];
            }
            else {
                $sections['stu_grad'] = [
                    'total_components' => $num_components * $peers_to_grade,
                    'non_late_total_components' => $num_components * $peers_to_grade,
                    'graded_components' => $my_grading, 'verified_components' => $my_grading,
                    'non_late_graded_components' => $my_grading, 'non_late_verified_components' => $my_grading,
                    'num_gradeables' => $num_gradeables, 'ta_graded_components' => 0,
                    'graders' => [], 'valid_graders' => []
                ];
            }
            $sections['all'] = [
                'total_components' => 0, 'graded_components' => 0, 'verified_components' => 0,
                'non_late_total_components' => 0, 'non_late_graded_components' => 0,
                'non_late_verified_components' => 0, 'graders' => [], 'valid_graders' => []
            ];
            foreach ($total_users as $key => $value) {
                if ($key == 'NULL') {
                    continue;
                }
                $sections['all']['total_components'] += $value * $num_components;
                $sections['all']['graded_components'] += isset($graded_components[$key]) ? $graded_components[$key] : 0;
                $sections['all']['verified_components'] += $verified_components[$key] ?? 0;
            }
            $sections['all']['total_components'] -= $num_components;
            $sections['all']['graded_components'] -= $my_grading;
            $sections['all']['verified_components'] -= $my_grading;
            $sections['all']['non_late_total_components'] = $sections['all']['total_components'];
            $sections['all']['non_late_graded_components'] = $sections['all']['graded_components'];
            $sections['all']['non_late_verified_components'] = $sections['all']['verified_components'];
            if ($gradeable->isTeamAssignment()) {
                $sections['stu_grad']['no_team'] = 0;
                $sections['stu_grad']['team'] = 0;
                $sections['all']['no_team'] = 0;
                $sections['all']['team'] = 0;
            }
        }
        else {
            foreach ($total_users as $key => $value) {
                if (array_key_exists($key, $num_submitted)) {
                    $sections[$key] = [
                        'total_components' => $num_submitted[$key],
                        'non_late_total_components' => $num_submitted[$key] - (array_key_exists($key, $late_submitted) ? $late_submitted[$key] : 0),
                        'graded_components' => 0, 'verified_components' => 0,
                        'non_late_graded_components' => 0, 'non_late_verified_components' => 0,
                        'ta_graded_components' => 0, 'graders' => [], 'valid_graders' => []
                    ];
                }
                else {
                    $sections[$key] = [
                        'total_components' => 0, 'graded_components' => 0, 'verified_components' => 0,
                        'non_late_total_components' => 0, 'non_late_graded_components' => 0,
                        'non_late_verified_components' => 0, 'graders' => [], 'valid_graders' => []
                    ];
                }
                if ($gradeable->isTeamAssignment()) {
                    $sections[$key]['no_team'] = $no_team_users[$key];
                    $sections[$key]['team'] = $team_users[$key];
                }
                if (isset($graded_components[$key])) {
                    $sections[$key]['graded_components'] = $graded_components[$key];
                    $sections[$key]['non_late_graded_components'] = $graded_components[$key] - $late_components[$key];
                    $sections[$key]['ta_graded_components'] = min(intval($graded_components[$key]), $sections[$key]['total_components']);
                }
                if (isset($verified_components[$key])) {
                    $sections[$key]['verified_components'] = $verified_components[$key];
                    $sections[$key]['non_late_verified_components'] = $verified_components[$key];
                }
                if (isset($graders[$key])) {
                    $sections[$key]['graders'] = $graders[$key];
                    if ((array_key_exists('include_null_registration', $_COOKIE) && $_COOKIE['include_null_registration'] === 'true') || $key !== "NULL") {
                        $valid_graders = [];
                        foreach ($graders[$key] as $valid_grader) {
                            if ($this->core->getAccess()->canUser($valid_grader, "grading.electronic.grade", ["gradeable" => $gradeable])) {
                                $valid_graders[] = $valid_grader->getDisplayedGivenName();
                            }
                        }
                        $sections[$key]["valid_graders"] = $valid_graders;
                    }
                }
            }
        }
        return $sections;
    }
}
