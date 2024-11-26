<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
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
    const NO_SELF_REGISTER = 0; // Self registration disabled
    const REQUEST_SELF_REGISTER = 1; // Self registration allowed, users request and instructors can approve
    const ALL_SELF_REGISTER = 2; // Self registration allowed, and all users who register are automatically added

    /**
     * @return MultiResponse
     */
    #[Route("/api/courses/{_semester}/{_course}/config", methods: ["GET"])]
    #[Route("/courses/{_semester}/{_course}/config", methods: ["GET"])]
    public function viewConfiguration(): MultiResponse {
        $fields = [
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
            'grade_inquiry_message'          => $this->core->getConfig()->getGradeInquiryMessage(),
            'private_repository'             => $this->core->getConfig()->getPrivateRepository(),
            'room_seating_gradeable_id'      => $this->core->getConfig()->getRoomSeatingGradeableId(),
            'seating_only_for_instructor'    => $this->core->getConfig()->isSeatingOnlyForInstructor(),
            'self_registration_type'         => $this->core->getQueries()->getSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse()),
            'registration_sections'          => $this->core->getQueries()->getRegistrationSections(),
            'default_section'                => $this->core->getQueries()->getDefaultRegistrationSection($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse()),
            'auto_rainbow_grades'            => $this->core->getConfig()->getAutoRainbowGrades(),
            'queue_enabled'                  => $this->core->getConfig()->isQueueEnabled(),
            'queue_message'                  => $this->core->getConfig()->getQueueMessage(),
            'seek_message_enabled'           => $this->core->getConfig()->isSeekMessageEnabled(),
            'seek_message_instructions'      => $this->core->getConfig()->getSeekMessageInstructions(),
            'queue_announcement_message'     => $this->core->getConfig()->getQueueAnnouncementMessage(),
            'polls_enabled'                  => $this->core->getConfig()->isPollsEnabled()
        ];
        $seating_options = $this->getGradeableSeatingOptions();
        $admin_in_course = false;
        if ($this->core->getConfig()->isSubmittyAdminUserVerified()) {
            $admin_in_course =  $this->core->getQueries()->checkIsInstructorInCourse(
                $this->core->getConfig()->getVerifiedSubmittyAdminUser(),
                $this->core->getConfig()->getCourse(),
                $this->core->getConfig()->getTerm()
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
     * @return MultiResponse
     */
    #[Route("/api/courses/{_semester}/{_course}/config", methods: ["POST"])]
    #[Route("/courses/{_semester}/{_course}/config", methods: ["POST"])]
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
            $gradeable_ids = [];
            foreach ($gradeable_seating_options as $option) {
                $gradeable_ids[] = $option['g_id'];
            }
            if (!in_array($entry, $gradeable_ids)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Invalid gradeable chosen for seating')
                );
            }
        }
        elseif (in_array($name, ['default_hw_late_days', 'default_student_late_days'])) {
            if (!ctype_digit($entry)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Must enter a number for this field')
                );
            }
            if ($entry > 10000) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Value must be less than or equal to 10000')
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
                    'seating_only_for_instructor',
                    'queue_enabled',
                    'seek_message_enabled',
                    'polls_enabled'
                ]
            )
        ) {
            $entry = $entry === "true";
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

            // If a manual_customization.json does not exist, then check for the presence of a regular one
            if (!$customization_json->doesManualCustomizationExist()) {
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

            $entry = $entry === "true";
        }

        if ($name === 'all_self_registration') {
            $this->core->getQueries()->setSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse(), $entry === 'true' ?  ConfigurationController::ALL_SELF_REGISTER : ConfigurationController::NO_SELF_REGISTER);
            $this->core->getQueries()->setDefaultRegistrationSection($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse(), $_POST['default_section']);
            $name = 'self_registration_type';
            $entry = $entry === 'true' ? ConfigurationController::ALL_SELF_REGISTER : ConfigurationController::NO_SELF_REGISTER;
        }

        if ($name === 'default_section_id') {
            $this->core->getQueries()->setDefaultRegistrationSection($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse(), $entry);
        }

        if ($name === 'forum_enabled' && $entry == 1) {
            // Only create default categories when there is no existing categories (only happens when first enabled)
            if (empty($this->core->getQueries()->getCategories())) {
                $categories = ["General Questions", "Homework Help", "Quizzes" , "Tests"];
                foreach ($categories as $rank => $category) {
                    $this->core->getQueries()->addNewCategory($category, $rank);
                }
            }
        }

        $config_json = $this->core->getConfig()->getCourseJson();
        if (!isset($config_json['course_details'][$name]) && !str_contains($name, 'self_registration') && $name !== 'default_section_id') {
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

        // All late day cache now invalid
        if ($name === 'default_student_late_days') {
            $this->core->getQueries()->flushAllLateDayCache();
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
