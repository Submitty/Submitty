<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\ForumUtils;
use app\libraries\response\JsonResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\models\RainbowCustomizationJSON;
use app\views\admin\ConfigurationView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ConfigurationController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class ConfigurationController extends AbstractController {

    // The message that should be returned to the user if they fail the required validation to enable the nightly
    // rainbow grades build checkbox
    const FAIL_AUTO_RG_MSG = 'You may not enable automatic rainbow grades generation until you have supplied a ' .
    'customization.json file.  To have one generated for you, you may use the Web-Based Rainbow Grades Generation inside the Grade ' .
    'Reports tab.  You may also manually create the file and upload it to your course\'s rainbow_grades directory.';

    /**
     * @Route("/api/{_semester}/{_course}/config", methods={"GET"})
     * @Route("/{_semester}/{_course}/config", methods={"GET"})
     * @return MultiResponse
     */
    public function viewConfiguration(): MultiResponse {
        $fields = array(
            'course_name'                    => $this->core->getConfig()->getCourseName(),
            'course_home_url'                => $this->core->getConfig()->getCourseHomeUrl(),
            'default_hw_late_days'           => $this->core->getConfig()->getDefaultHwLateDays(),
            'default_student_late_days'      => $this->core->getConfig()->getDefaultStudentLateDays(),
            'zero_rubric_grades'             => $this->core->getConfig()->shouldZeroRubricGrades(),
            'upload_message'                 => $this->core->getConfig()->getUploadMessage(),
            'display_rainbow_grades_summary' => $this->core->getConfig()->displayRainbowGradesSummary(),
            'display_custom_message'         => $this->core->getConfig()->displayCustomMessage(),
            'course_email'                   => $this->core->getConfig()->getCourseEmail(),
            'vcs_base_url'                   => $this->core->getConfig()->getVcsBaseUrl(),
            'vcs_type'                       => $this->core->getConfig()->getVcsType(),
            'forum_enabled'                  => $this->core->getConfig()->isForumEnabled(),
            'forum_create_thread_message'    => $this->core->getConfig()->getForumCreateThreadMessage(),
            'regrade_enabled'                => $this->core->getConfig()->isRegradeEnabled(),
            'regrade_message'                => $this->core->getConfig()->getRegradeMessage(),
            'private_repository'             => $this->core->getConfig()->getPrivateRepository(),
            'room_seating_gradeable_id'      => $this->core->getConfig()->getRoomSeatingGradeableId(),
            'seating_only_for_instructor'    => $this->core->getConfig()->isSeatingOnlyForInstructor(),
            'auto_rainbow_grades'            => $this->core->getConfig()->getAutoRainbowGrades(),
            'queue_enabled'                  => $this->core->getConfig()->isQueueEnabled(),
            'queue_contact_info'             => $this->core->getConfig()->getQueueContactInfo(),
            'queue_message'                  => $this->core->getConfig()->getQueueMessage(),
        );
        $seating_options = $this->getGradeableSeatingOptions();
        $admin_in_course = false;
        if ($this->core->getConfig()->isSubmittyAdminUserVerified()) {
            $admin_in_course =  $this->core->getQueries()->checkIsInstructorInCourse(
                $this->core->getConfig()->getVerifiedSubmittyAdminUser(),
                $this->core->getConfig()->getCourse(),
                $this->core->getConfig()->getSemester()
            );
        }

        return new MultiResponse(
            JsonResponse::getSuccessResponse([
                'config' => $fields,
                'gradeable_seating_options' => $seating_options,
                'email_enabled' => $this->core->getConfig()->isEmailEnabled(),
                'submitty_admin_user' => [
                    'user_id' => $this->core->getConfig()->getVerifiedSubmittyAdminUser(),
                    'verified' => $this->core->getConfig()->isSubmittyAdminUserVerified(),
                    'in_course' => $admin_in_course,
                ]
            ]),
            new WebResponse(
                ConfigurationView::class,
                'viewConfig',
                $fields,
                $seating_options,
                $this->core->getConfig()->isEmailEnabled(),
                [
                    'user_id' => $this->core->getConfig()->getVerifiedSubmittyAdminUser(),
                    'verified' => $this->core->getConfig()->isSubmittyAdminUserVerified(),
                    'in_course' => $admin_in_course,
                ],
                $this->core->getCsrfToken()
            )
        );
    }

    /**
     * @Route("/api/{_semester}/{_course}/config", methods={"POST"})
     * @Route("/{_semester}/{_course}/config", methods={"POST"})
     * @return MultiResponse
     */
    public function updateConfiguration(): MultiResponse {
        if (!isset($_POST['name'])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Name of config value not provided')
            );
        }
        $name = $_POST['name'];

        if (!isset($_POST['entry'])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Name of config entry not provided')
            );
        }
        $entry = $_POST['entry'];

        if ($name === "room_seating_gradeable_id") {
            $gradeable_seating_options = $this->getGradeableSeatingOptions();
            $gradeable_ids = array();
            foreach ($gradeable_seating_options as $option) {
                $gradeable_ids[] = $option['g_id'];
            }
            if (!in_array($entry, $gradeable_ids)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Invalid gradeable chosen for seating')
                );
            }
        }
        elseif (in_array($name, array('default_hw_late_days', 'default_student_late_days'))) {
            if (!ctype_digit($entry)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Must enter a number for this field')
                );
            }
            $entry = intval($entry);
        }
        elseif (
            in_array(
                $name,
                [
                    'zero_rubric_grades',
                    'display_rainbow_grades_summary',
                    'display_custom_message',
                    'forum_enabled',
                    'regrade_enabled',
                    'seating_only_for_instructor',
                    'queue_enabled',
                    'queue_contact_info'
                ]
            )
        ) {
            $entry = $entry === "true" ? true : false;
        }
        elseif ($name == "course_home_url") {
            if (!filter_var($entry, FILTER_VALIDATE_URL) && !empty($entry)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($entry . ' is not a valid URL')
                );
            }
        }
        elseif ($name === 'auto_rainbow_grades') {
            // Special validation for auto_rainbow_grades checkbox
            // Get a new customization json object
            $customization_json = new RainbowCustomizationJSON($this->core);

            // If a custom_customization.json does not exist, then check for the presence of a regular one
            if (!$customization_json->doesCustomCustomizationExist()) {
                // Attempt to populate it from the customization.json in the course rainbow_grades directory
                // If no file exists do not allow user to enable this check mark until one is supplied
                try {
                    $customization_json->loadFromJsonFile();
                }
                catch (\Exception $e) {
                    return MultiResponse::JsonOnlyResponse(
                        JsonResponse::getFailResponse(ConfigurationController::FAIL_AUTO_RG_MSG)
                    );
                }
            }

            $entry = $entry === "true" ? true : false;
        }

        if ($name === 'forum_enabled' && $entry == 1) {
            // Only create default categories when there is no existing categories (only happens when first enabled)
            if (empty($this->core->getQueries()->getCategories())) {
                $categories = ["General Questions", "Homework Help", "Quizzes" , "Tests"];
                foreach ($categories as $category) {
                    $this->core->getQueries()->addNewCategory($category);
                }
            }
        }

        $config_json = $this->core->getConfig()->getCourseJson();
        if (!isset($config_json['course_details'][$name])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Not a valid config name')
            );
        }
        $config_json['course_details'][$name] = $entry;

        if (!$this->core->getConfig()->saveCourseJson(['course_details' => $config_json['course_details']])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Could not save config file')
            );
        }

        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(null)
        );
    }

    private function getGradeableSeatingOptions(): array {
        $gradeable_seating_options = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        $seating_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'seating');

        $gradeable_seating_options = array_filter($gradeable_seating_options, function ($seating_option) use ($seating_dir) {
            return is_dir(FileUtils::joinPaths($seating_dir, $seating_option['g_id']));
        });

        return array_merge([['g_id' => '', 'g_title' => '--None--']], $gradeable_seating_options);
    }
}
