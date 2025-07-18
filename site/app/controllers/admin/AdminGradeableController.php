<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\CodeMirrorUtils;
use app\exceptions\ValidationException;
use app\libraries\DateUtils;
use app\libraries\Utils;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\Component;
use app\models\gradeable\GradeableUtils;
use app\models\gradeable\Mark;
use app\libraries\FileUtils;
use app\libraries\response\DownloadResponse;
use app\libraries\response\JsonResponse;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AdminGradeableController
 * @package app\controllers\admin
 */
#[AccessControl(role: "INSTRUCTOR")]
class AdminGradeableController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/update", methods: ["GET"])]
    public function editGradeableRequest($gradeable_id, $nav_tab = 0) {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
            $this->editPage($gradeable, $this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse(), intval($nav_tab));
        }
        catch (\InvalidArgumentException $e) {
            // If the gradeable can't be found, redirect to new page
            $this->newPage();
        }
    }

    const syllabus_buckets = [
        'homework', 'assignment', 'problem-set',
        'quiz', 'test', 'exam',
        'exercise', 'lecture-exercise', 'reading', 'lab', 'recitation', 'worksheet',
        'project',
        'participation', 'note',
        'none (for practice only)'];

    const gradeable_type_strings = [
        'checkpoint' => 'Checkpoints (simple data entry: full/half/no credit)',
        'numeric' => 'Numeric/Text (simple data entry: integer or floating point and/or short text strings)',
        'electronic_hw' => 'Students will submit one or more files by direct upload to the Submitty website',
        'electronic_hw_vcs' => 'Students will submit by committing files to a version control system (VCS) repository',
        'electronic_bulk' => 'TA/Instructor will (bulk) upload scanned .pdf for online manual grading'
    ];

    /**
     * Creates a gradeable based on uploaded JSON data
     */
    #[Route("/api/{_semester}/{_course}/upload", methods: ["POST"])]
    #[Route("/courses/{_semester}/{_course}/upload", methods: ["POST"])]
    public function uploadGradeable(): JsonResponse {
        $values = [
            'title' => '',
            'instructions_url' => '',
            'id' => '',
            'type' => '',
            'bulk_upload' => false,
            'vcs' => false,
            'ta_grading' => false,
            'grade_inquiry_allowed' => false,
            'grade_inquiry_per_component_allowed' => false,
            'discussion_based' => false,
            'discussion_thread_id' => '',
            'team_assignment' => false,
            'team_size_max' => 3,
            'eg_inherit_teams_from' => '',
            'gradeable_teams_read' => false,
            'vcs_radio_buttons' => 'submitty-hosted',
            'external_repo' => '',
            'using_subdirectory' => false,
            'vcs_subdirectory' => '',
            'syllabus_bucket' => 'homework',
            'autograding_config_path' => ''
        ];

        if (!isset($_POST['id']) || !isset($_POST['title']) || !isset($_POST['type'])) {
            return JsonResponse::getErrorResponse('JSON requires id, title, and type. See documentation for information');
        }

        $values['id'] = $_POST['id'];
        $values['title'] = $_POST['title'];
        $values['type'] = $_POST['type'];
        $values['autograding_config_path'] = $_POST['autograding_config_path'] ?? FileUtils::joinPaths($this->core->getConfig()->getSubmittyInstallPath(), 'more_autograding_examples/upload_only/config');
        if ($_POST['type'] === 'Electronic File') {
            if (array_key_exists('vcs', $_POST)) {
                if (!array_key_exists('repository_type', $_POST['vcs'])) {
                    return JsonResponse::getErrorResponse('VCS gradeables require a repository_type value. See documentation for information.');
                }
                if (!in_array($_POST['vcs']['repository_type'], ['submitty-hosted', 'submitty-hosted-url', 'public-github', 'private-github', 'self-hosted'], true)) {
                    return JsonResponse::getErrorResponse('VCS gradeables requires a valid vcs_radio_buttons value. See documentation for information.');
                }
                if (!array_key_exists('vcs_path', $_POST['vcs'])) {
                    return JsonResponse::getErrorResponse('VCS gradeables require a vcs_path. See documentation for information.');
                }
                elseif ($_POST['vcs']['repository_type'] === 'self-hosted') {
                    $values['external_repo'] = $_POST['vcs']['vcs_path'];
                }
                if (isset($_POST['vcs']['vcs_subdirectory'])) {
                    $values['using_subdirectory'] = true;
                    $values['vcs_subdirectory'] = $_POST['vcs']['vcs_subdirectory'];
                }
                $values['vcs'] = true;
                $values['vcs_radio_buttons'] = $_POST['vcs']['repository_type'];
                $values['vcs_path'] = $_POST['vcs']['vcs_path'];
            }
            $values['bulk_upload'] = Utils::getBooleanValue($_POST['bulk_upload'] ?? false);
        }

        if (array_key_exists('team_gradeable', $_POST)) {
            if (!array_key_exists('team_size_max', $_POST['team_gradeable'])) {
                return JsonResponse::getErrorResponse('Team gradeables require a team_size_max value. See documentation for information.');
            }
            $values['eg_inherit_teams_from'] = $_POST['team_gradeable']['inherit_from'] ?? '';
            $values['team_assignment'] = true;
            $values['team_size_max'] = $_POST['team_gradeable']['team_size_max'];
        }
        if (array_key_exists('discussion_thread_id', $_POST)) {
            $values['discussion_based'] = Utils::getBooleanValue($_POST['discussion_based'] ?? false);
            $values['discussion_thread_id'] = $_POST['discussion_thread_id'];
        }
        if (array_key_exists('ta_grading', $_POST)) {
            $values['ta_grading'] = Utils::getBooleanValue($_POST['ta_grading']);
            if (array_key_exists('grade_inquiries', $_POST)) {
                $values['grade_inquiry_allowed'] = Utils::getBooleanValue($_POST['grade_inquiries'] ?? false);
                $values['grade_inquiry_per_component_allowed'] = Utils::getBooleanValue($_POST['grade_inquiries_per_component'] ?? false);
            }
        }

        if (array_key_exists('dates', $_POST)) {
            $dates = $_POST['dates'];
            $values['ta_view_start_date'] = $dates['ta_view_start_date'] ?? null;
            $values['grade_start_date'] = $dates['grade_start_date'] ?? null;
            $values['grade_due_date'] = $dates['grade_due_date'] ?? null;
            $values['grade_released_date'] = $dates['grade_released_date'] ?? null;
            $values['team_lock_date'] = $dates['team_lock_date'] ?? null;
            $values['submission_open_date'] = $dates['submission_open_date'] ?? null;
            $values['submission_due_date'] = $dates['submission_due_date'] ?? null;
            $values['grade_inquiry_start_date'] = $dates['grade_inquiry_start_date'] ?? null;
            $values['grade_inquiry_due_date'] = $dates['grade_inquiry_due_date'] ?? null;

            $values['has_due_date'] = $dates['has_due_date'] ?? true;
            $values['has_release_date'] = $dates['has_released_date'] ?? true;
            $values['late_submission_allowed'] = $dates['late_submission_allowed'] ?? true;
            $values['late_days'] = $dates['late_days'] ?? 0;
        }
        $values['syllabus_bucket'] = $_POST['syllabus_bucket'] ?? 'homework';
        try {
            $build_result = $this->createGradeable($_POST['id'], $values);
            // Finally, redirect to the edit page
            if ($build_result !== null) {
                return JsonResponse::getErrorResponse($build_result);
            }
            $rubric_components = [];
            if (isset($_POST['rubric'])) {
                $gradeable = $this->tryGetGradeable($values['id']);
                // Delete the default blank component
                $gradeable->deleteComponent($gradeable->getComponents()[0]);
                foreach ($_POST['rubric'] as $rubric_component) {
                    $component_values = [
                        'title',
                        'ta_comment',
                        'student_comment',
                        'text',
                        'peer_component',
                        'page',
                    ];
                    if (count(array_diff($component_values, array_keys($rubric_component))) !== 0) {
                        $this->deleteGradeable($values['id']);
                        return JsonResponse::getErrorResponse('Rubric component does not have all of the parameters');
                    }
                    try {
                        $rubric_components[] = $gradeable->importComponent($rubric_component);
                    }
                    catch (\OutOfBoundsException $exception) {
                        // Delete gradeable as to not leave the gradeable in a 'broken' state, and users can fix the JSON file and create a
                        // fully functioning gradeable in one go instead of debugging a non-complete gradeable
                        $this->deleteGradeable($values['id']);
                        return JsonResponse::getErrorResponse('Rubric component has extra parameters: ' . $exception->getMessage());
                    }
                    catch (\Exception $exception) {
                        $this->deleteGradeable($values['id']);
                        return JsonResponse::getErrorResponse('An error has occurred: ' . $exception->getMessage());
                    }
                }
                // Save to the database
                $this->core->getQueries()->updateGradeable($gradeable);
            }
            return JsonResponse::getSuccessResponse($values['id']);
        }
        catch (ValidationException | \Exception $e) {
            return JsonResponse::getErrorResponse('An error has occurred: ' . $e->getMessage());
        }
    }

    #[Route("/api/{_semester}/{_course}/{gradeable_id}/download", methods: ["GET"])]
    public function apiDownloadJson(string $gradeable_id): JsonResponse {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $exception) {
            return JsonResponse::getErrorResponse($exception->getMessage());
        }
        catch (\Exception $exception) {
            return JsonResponse::getErrorResponse($exception->getMessage());
        }
        return JsonResponse::getSuccessResponse($this->getGradeableJson($gradeable));
    }

    #[Route("/courses/{_semester}/{_course}/{gradeable_id}/download", methods: ["GET"])]
    public function webDownloadJson(string $gradeable_id): DownloadResponse {
        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        return DownloadResponse::getDownloadResponse(
            json_encode($this->getGradeableJson($gradeable), JSON_PRETTY_PRINT),
            $gradeable->getId() . '.json'
        );
    }

    /**
     * Returns a JSON array to recreate the gradeable using
     * the 'uploadGradeable' function.
     * @param Gradeable $gradeable
     * @return array{
     *     title: string|mixed,
     *     type: string|mixed,
     *     id: string|mixed,
     *     instructions_url: string|mixed,
     *     syllabus_bucket: string|mixed,
     *     autograding_config_path: string|mixed,
     *     bulk_upload: boolean,
     *     team_gradeable?: array{
     *         team_size_max: int,
     *         inherit_from: string|mixed,
     *     },
     *     ta_grading?: boolean,
     *     grade_inquiries?: boolean,
     *     grade_inquiries_per_component?: boolean,
     *     discussion_based?: boolean,
     *     discussion_thread_id?: boolean,
     *     vcs?: array{
     *         repository_type?: string|mixed,
     *         vcs_path?: string|mixed,
     *         vcs_subdirectory?: string|mixed,
     *     },
     *     dates: array{
     *        ta_view_start_date: string|mixed,
     *        grade_start_date: string|mixed,
     *        grade_due_date: string|mixed,
     *        grade_released_date: string|mixed,
     *        team_lock_date: string|mixed,
     *        submission_open_date: string|mixed,
     *        submission_due_date: string|mixed,
     *        grade_inquiry_start_date: string|mixed,
     *        grade_inquiry_due_date: string|mixed,
     *        has_due_date: boolean,
     *        has_release_date: boolean,
     *        late_submission_allowed: boolean,
     *        late_days: integer,
     *     }
     * }
     */
    public function getGradeableJson(Gradeable $gradeable): array {
        $return_json = [
            'title' => $gradeable->getTitle(),
            'type' => GradeableType::typeToString($gradeable->getType()),
            'id' => $gradeable->getId(),
            'instructions_url' => $gradeable->getInstructionsUrl(),
            'syllabus_bucket' => $gradeable->getSyllabusBucket(),
            'autograding_config_path' => $gradeable->getAutogradingConfigPath()
        ];
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $return_json['bulk_upload'] = $gradeable->isBulkUpload();
            if ($gradeable->isTeamAssignment()) {
                $team_properties = [
                    'team_size_max' => $gradeable->getTeamSizeMax(),
                    'inherit_from' => ''
                ];
                $return_json['team_gradeable'] = $team_properties;
            }
            if ($gradeable->isTaGrading()) {
                $return_json['ta_grading'] = true;
                if ($gradeable->isGradeInquiryAllowed()) {
                    $return_json['grade_inquiries'] = true;
                    if ($gradeable->isGradeInquiryPerComponentAllowed()) {
                        $return_json['grade_inquiries_per_component'] = true;
                    }
                }
            }
            if ($gradeable->isDiscussionBased()) {
                $return_json['discussion_based'] = true;
                $return_json['discussion_thread_id'] = $gradeable->getDiscussionThreadId();
            }
            if ($gradeable->isVcs()) {
                $vcs_values = [];
                switch ($gradeable->getVcsHostType()) {
                    case 0:
                        $vcs_values['repository_type'] = 'submitty-hosted';
                        break;
                    case 1:
                        $vcs_values['repository_type'] = 'submitty-hosted-url';
                        $vcs_values['vcs_path'] = $gradeable->getVcsPartialPath();
                        break;
                    case 2:
                        $vcs_values['repository_type'] = 'public-github';
                        break;
                    case 3:
                        $vcs_values['repository_type'] = 'private-github';
                        break;
                    case 4:
                        $vcs_values['repository_type'] = 'self-hosted';
                        $vcs_values['vcs_path'] = $gradeable->getVcsPartialPath();
                        break;
                    default:
                        $vcs_values['repository_type'] = 'invalid-type';
                        break;
                }
                if ($gradeable->isUsingSubdirectory()) {
                    $vcs_values['subdirectory'] = $gradeable->getVcsSubdirectory();
                }
                $return_json['vcs'] = $vcs_values;
            }

            $dates = [];
            $dates['ta_view_start_date'] = $gradeable->getTaViewStartDate()->format('Y-m-d H:i:s');
            $dates['grade_start_date'] = $gradeable->getGradeStartDate()->format('Y-m-d H:i:s');
            $dates['grade_due_date'] = $gradeable->getGradeDueDate()->format('Y-m-d H:i:s');
            $dates['grade_released_date'] = $gradeable->getGradeReleasedDate()->format('Y-m-d H:i:s');
            $dates['team_lock_date'] = $gradeable->getTeamLockDate()->format('Y-m-d H:i:s');
            $dates['submission_open_date'] = $gradeable->getSubmissionOpenDate()->format('Y-m-d H:i:s');
            $dates['submission_due_date'] = $gradeable->getSubmissionDueDate()->format('Y-m-d H:i:s');
            $dates['grade_inquiry_start_date'] = $gradeable->getGradeInquiryStartDate()->format('Y-m-d H:i:s');
            $dates['grade_inquiry_due_date'] = $gradeable->getGradeInquiryDueDate()->format('Y-m-d H:i:s');

            $dates['has_due_date'] = $gradeable->hasDueDate();
            $dates['has_release_date'] = $gradeable->hasReleaseDate();
            $dates['late_submission_allowed'] = $gradeable->isLateSubmissionAllowed();
            $dates['late_days'] = $gradeable->getLateDays();
            $return_json['dates'] = $dates;
            $return_json['rubric'] = $gradeable->exportComponents();
        }
        return $return_json;
    }

    /**
     * Displays the 'new' page, populating the first-page properties with the
     *  provided gradeable's data
     * @param string|null $template_id
     */
    #[Route("/courses/{_semester}/{_course}/gradeable", methods: ["GET"])]
    public function newPage($template_id = null) {
        $this->core->getOutput()->addBreadcrumb("New Gradeable");

        $gradeable = $template_id ? $this->core->getQueries()->getGradeableConfig($template_id) : null;

        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $submit_url = $this->core->buildCourseUrl(['gradeable']);
        $vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addSelect2WidgetCSSAndJs();
        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        $this->core->getOutput()->addInternalJs('directory.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');
        $this->core->getOutput()->renderTwigOutput('admin/admin_gradeable/AdminGradeableBase.twig', [
            'submit_url' => $submit_url,
            'gradeable' => $gradeable,
            'vcs_subdirectory' => '',
            'using_subdirectory' => false,
            'action' => $gradeable !== null ? 'template' : 'new',
            'template_list' => $template_list,
            'syllabus_buckets' => self::syllabus_buckets,
            'vcs_base_url' => $vcs_base_url,
            'vcs_partial_path' => '',
            'forum_enabled' => $this->core->getConfig()->isForumEnabled(),
            'gradeable_type_strings' => self::gradeable_type_strings,
            'csrf_token' => $this->core->getCsrfToken(),
            'notifications_sent' => 0,
            'notifications_pending' => 0
        ]);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage(Gradeable $gradeable, $semester, $course, $nav_tab = 0) {
        $this->core->getOutput()->addBreadcrumb('Edit Gradeable');
        // Serialize the components for numeric/checkpoint rubrics
        $gradeable_components_enc = array_map(function (Component $c) {
            return $c->toArray();
        }, $gradeable->getComponents());

        $num_checkpoints = 0;
        $num_text = 0;
        foreach ($gradeable->getComponents() as $component) {
            if ($component->isText()) {
                $num_text++;
            }
            else {
                $num_checkpoints++;
            }
        }

        // Construct history array, first indexed by user type, then by gradeable id
        $gradeable_section_history = [];
        $graders_from_usertypes = $this->core->getQueries()->getGradersByUserType();
        foreach ($graders_from_usertypes as $usertype) {
            foreach ($usertype as $grader) {
                $gradeable_section_history[$grader[0]] = [];
            }
        }

        // Construct a list of rotating gradeables
        $rotating_gradeables = [];
        foreach ($this->core->getQueries()->getGradeablesRotatingGraderHistory($gradeable->getId()) as $row) {
            $gradeable_section_history[$row['user_id']][$row['g_id']] = $row['sections_rotating_id'];

            // Use the keys to remove duplicates
            $rotating_gradeables[$row['g_id']] = 1;
        }
        $rotating_gradeables = array_keys($rotating_gradeables);

        // The current gradeable will always load its grader history,
        // but if it is grade by registration it should not be in $rotating_gradeables array
        if ($gradeable->getGraderAssignmentMethod() == Gradeable::REGISTRATION_SECTION) {
            $current_g_id_key = array_search($gradeable->getId(), $rotating_gradeables);
            unset($rotating_gradeables[$current_g_id_key]);
            $rotating_gradeables = array_values($rotating_gradeables);
        }

        // Get some global configuration data
        $num_rotating_sections = $this->core->getQueries()->getNumberRotatingSections();
        $default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        $vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();

        // These are hard coded default config options.
        $default_config_paths = $gradeable->getDefaultConfigPaths();

        // Configs uploaded to the 'Upload Gradeable Config' page
        $uploaded_configs_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'config_upload');
        $all_uploaded_configs = FileUtils::getAllFiles($uploaded_configs_dir);
        $all_uploaded_config_paths = [];
        foreach ($all_uploaded_configs as $file) {
            $all_uploaded_config_paths[] = [ 'UPLOADED: ' . substr($file['path'], strlen($uploaded_configs_dir) + 1) , $file['path'] ];
        }
        // Configs stored in a private repository (specified in course config)
        $config_repo_string = $this->core->getConfig()->getPrivateRepository();
        $all_repository_config_paths = [];
        $repository_error_messages = [];
        $repo_id_number = 1;
        foreach (explode(',', $config_repo_string) as $config_repo_name) {
            $config_repo_name = str_replace(' ', '', $config_repo_name);
            if ($config_repo_name == '') {
                continue;
            }
            $directory_queue = [$config_repo_name];
            $repo_paths = $this->getValidPathsToConfigDirectories($directory_queue, $repository_error_messages, $repo_id_number);
            if (!empty($repo_paths)) {
                $all_repository_config_paths = array_merge($all_repository_config_paths, $repo_paths);
            }
            $repo_id_number++;
        }
        usort($all_repository_config_paths, function ($a, $b) {
            return strcmp($a[0], $b[0]);
        });

        $type_string = 'UNKNOWN';
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            if ($gradeable->isBulkUpload()) {
                $type_string = self::gradeable_type_strings['electronic_bulk'];
            }
            elseif ($gradeable->isVcs()) {
                $type_string = self::gradeable_type_strings['electronic_hw_vcs'];
            }
            else {
                $type_string = self::gradeable_type_strings['electronic_hw'];
            }
        }
        elseif ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $type_string = self::gradeable_type_strings['numeric'];
        }
        elseif ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $type_string = self::gradeable_type_strings['checkpoint'];
        }

        //true if there are no students in any rotating sections.
        //Can sometimes be true even if $num_rotating_sections > 0 (if no students are in any section)
        $no_rotating_sections = true;
        foreach ($this->core->getQueries()->getUsersCountByRotatingSections() as $section) {
            if ($section['rotating_section'] != null && $section['count'] > 0) {
                $no_rotating_sections = false;
                break;
            }
        }

        // Get the list of itempool questions in this gradeable which are multi-valued (and hence randomized)
        $itempool_options = [];
        // read config file

        if ($gradeable->hasAutogradingConfig()) {
            $gradeable_config = $gradeable->getAutogradingConfig();
            $notebook_config = $gradeable_config->getNotebookConfig();


            // loop through the notebook key, and find from_pool key in each object (or question)
            foreach ($notebook_config as $key => $item) {
                // store those question which are having count(from_pool array) > 1
                if (isset($item['from_pool']) && count($item['from_pool']) > 1) {
                    $item_id = !empty($item['item_label']) ? $item["item_label"] : "item";
                    if (!isset($itempool_options[$item_id])) {
                        $itempool_options[$item_id] = $item['from_pool'];
                    }
                    else {
                        $itempool_options[$item_id . '_' . $key] = $item['from_pool'];
                    }
                }
            }
        }
        $config_files = FileUtils::getAllFiles($gradeable->getAutogradingConfigPath());
        // $this->inherit_teams_list = $this->core->getQueries()->getAllElectronicGradeablesWithBaseTeams();
        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        $gradeable_max_points = ["" => 0];
        $gradeables = $this->core->getQueries()->getGradeableConfigs(null);
        /** @var Gradeable $a_gradeable */
        foreach ($gradeables as $a_gradeable) {
            $auto_config = $a_gradeable->getAutogradingConfig();
            if ($auto_config != null) {
                $gradeable_max_points[$a_gradeable->getId()] = $auto_config->getTotalNonHiddenNonExtraCredit();
            }
        }
        $hasCustomMarks =  $this->core->getQueries()->getHasCustomMarks($gradeable->getId());
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('twigjs', 'twig.min.js'));
            $this->core->getOutput()->addInternalModuleJs('ta-grading-rubric-conflict.js');
            $this->core->getOutput()->addInternalModuleJs('ta-grading-rubric.js');
            $this->core->getOutput()->addInternalJs('gradeable.js');
            $this->core->getOutput()->addInternalCss('electronic.css');
        }
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        CodeMirrorUtils::loadDefaultDependencies($this->core);
        $this->core->getOutput()->addSelect2WidgetCSSAndJs();
        $this->core->getOutput()->addInternalJs('admin-gradeable-updates.js');
        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        $this->core->getOutput()->renderTwigOutput('admin/admin_gradeable/AdminGradeableBase.twig', [
            'num_checkpoints' => $num_checkpoints,
            'num_text_components' => $num_text,
            'gradeable' => $gradeable,
            'action' => 'edit',
            'nav_tab' => $nav_tab,
            'semester' => $semester,
            'course' => $course,
            'date_format' => 'Y-m-d H:i:s',
            'syllabus_buckets' => self::syllabus_buckets,
            'gradeable_components_enc' => json_encode($gradeable_components_enc),
            'grade_inquiry_allowed' => $gradeable->isGradeInquiryAllowed(),
            'forum_enabled' => $this->core->getConfig()->isForumEnabled(),
            'electronic' => $gradeable->getType() === GradeableType::ELECTRONIC_FILE,
            // Non-Gradeable-model data
            'gradeable_section_history' => $gradeable_section_history,
            'num_rotating_sections' => $num_rotating_sections,
            'no_rotating_sections' => $no_rotating_sections,
            'rotating_gradeables' => $rotating_gradeables,
            'graders_from_usertypes' => $graders_from_usertypes,
            //'inherit_teams_list' => $inherit_teams_list
            'default_late_days' => $default_late_days,
            'vcs_base_url' => $vcs_base_url,
            'vcs_partial_path' => $gradeable->getVcsPartialPath(),
            'vcs_subdirectory' => $gradeable->getVcsSubdirectory(),
            'download_url' => $this->core->buildCourseUrl([$gradeable->getId(), 'download']),
            'using_subdirectory' => $gradeable->isUsingSubdirectory(),
            'is_pdf_page' => $gradeable->isPdfUpload(),
            'is_pdf_page_student' => $gradeable->isStudentPdfUpload(),
            'itempool_available' => isset($gradeable_config) && $gradeable_config->isNotebookGradeable() && count($itempool_options),
            'itempool_options' => json_encode($itempool_options),
            'num_numeric' => $gradeable->getNumNumeric(),
            'num_text' => $gradeable->getNumText(),
            'type_string' => $type_string,
            'gradeable_type_strings' => self::gradeable_type_strings,
            'show_edit_warning' => $gradeable->anyManualGrades(),
            'isDiscussionPanel' => $gradeable->isDiscussionBased(),
            // Config selection data
            'all_config_paths' => array_merge($default_config_paths, $all_uploaded_config_paths, $all_repository_config_paths),
            'all_nonuploaded_config_paths' => array_merge($default_config_paths, $all_repository_config_paths),
            'repository_error_messages' => $repository_error_messages,
            'currently_valid_repository' => $this->checkPathToConfigFile($gradeable->getAutogradingConfigPath()),
            'selected_autograding_configuration_path' => $gradeable->getAutogradingConfigPath(),

            'timezone_string' => $this->core->getUser()->getUsableTimeZone()->getName(),

            'upload_config_url' => $this->core->buildCourseUrl(['autograding_config']) . '?g_id=' . $gradeable->getId(),
            'rebuild_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'rebuild']),
            'csrf_token' => $this->core->getCsrfToken(),
            'peer' => $gradeable->hasPeerComponent(),
            'peer_grader_pairs' => $this->core->getQueries()->getPeerGradingAssignment($gradeable->getId()),
            'notebook_builder_url' => $this->core->buildCourseUrl(['notebook_builder', $gradeable->getId()]),
            'hidden_files' => $gradeable->getHiddenFiles(),
            'template_list' => $template_list,
            'gradeable_max_points' =>  $gradeable_max_points,
            'allow_custom_marks' => $gradeable->getAllowCustomMarks(),
            'has_custom_marks' => $hasCustomMarks,
            'is_bulk_upload' => $gradeable->isBulkUpload(),
            'rainbow_grades_summary' => $this->core->getConfig()->displayRainbowGradesSummary(),
            'config_files' => $config_files,
            'notifications_sent' => $gradeable->getNotificationsSent(),
            'notifications_pending' => $this->core->getQueries()->getPendingGradeableNotifications($gradeable->getId())
        ]);
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'popupStudents');
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'popupMarkConflicts');
        $this->core->getOutput()->renderOutput(['admin', 'Gradeable'], 'AdminGradeableEditPeersForm', $gradeable);
        $this->core->getOutput()->renderOutput(['admin', 'Gradeable'], 'AdminGradeableAddPeersForm', $gradeable);
    }

    /**
     * Called when user presses submit on an Edit Students popup for peer matrix. Updates the database with
     *  the grader's new students.
     * @param String $gradeable_id
     */
    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/update_peer_assignment", methods: ["POST"])]
    public function editGraderPeerSubmit($gradeable_id) {
        $grader_id = $_POST['grader_id'];
        //if entire grader row is removed, just remove grader and their students
        if (!empty($_POST['remove_grader'])) {
            $this->core->getQueries()->removePeerAssignmentsForGrader($gradeable_id, $grader_id);
        }
        else {
            //otherwise, check if any of the individual current students were removed
            $tmp = $this->core->getQueries()->getPeerGradingAssignmentsForGrader($grader_id);
            $grading_assignment_for_grader = $tmp[$gradeable_id];
            foreach ($grading_assignment_for_grader as $i => $student_id) {
                if (!in_array($student_id, json_decode($_POST['curr_student_ids']), true)) {
                    $this->core->getQueries()->removePeerAssignment($gradeable_id, $grader_id, $student_id);
                }
            }
            // then, add new students
            foreach (json_decode($_POST['add_student_ids']) as $i => $student_id) {
                if ($student_id === $grader_id) {
                    return JsonResponse::getErrorResponse("Please note that student is not able to grade themselves");
                }
                $this->core->getQueries()->insertPeerGradingAssignment($grader_id, $student_id, $gradeable_id);
            }
        }
        // return new peer assignments to AJAX success
        $new_peers = $this->core->getQueries()->getPeerGradingAssignment($gradeable_id);
        return JsonResponse::getSuccessResponse($new_peers);
    }

    /**
     * Called when user presses submit on an Add New Grader to Matrix popup for peer matrix. Updates the
     * database with the grader's new students.
     * @param String $gradeable_id
     */
    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/new_peer_grader", methods: ["POST"])]
    public function newGraderPeerSubmit($gradeable_id) {
        $new_grader_id = $_POST['new_grader_id'];
        // add the new grader and all their students
        foreach (json_decode($_POST['add_student_ids']) as $i => $student_id) {
            $this->core->getQueries()->insertPeerGradingAssignment($new_grader_id, $student_id, $gradeable_id);
        }
        // return new peer assignments to AJAX success
        $new_peers = $this->core->getQueries()->getPeerGradingAssignment($gradeable_id);
        return JsonResponse::getSuccessResponse($new_peers);
    }

    /* Http request methods (i.e. ajax) */

    /**
     * TODO: This was extracted from the rubric saving code for electronic files
     */
    private function shufflePeerGrading(Gradeable $gradeable) {
        if ($gradeable->hasPeerComponent()) {
            //$old_peer_grading_assignments = $this->core->getQueries()->getPeerGradingAssignNumber($gradeable->getId());
            //$make_peer_assignments = ($old_peer_grading_assignments !== $gradeable->getPeerGradeSet());
            //if ($make_peer_assignments) {
            $this->core->getQueries()->clearPeerGradingAssignment($gradeable->getId());

            $users = $this->core->getQueries()->getAllUsers();
            $user_ids = [];
            $grading = [];
            $peer_grade_set = $gradeable->getPeerGradeSet();
            foreach ($users as $key => $user) {
                // Need to remove non-student users, or users in the NULL section
                if ($user->getRegistrationSection() == null) {
                    unset($users[$key]);
                }
                else {
                    $user_ids[] = $user->getId();
                    $grading[$user->getId()] = [];
                }
            }
            $user_number = count($user_ids);
            shuffle($user_ids);
            for ($i = 0; $i < $user_number; $i++) {
                for ($j = 1; $j <= $peer_grade_set; $j++) {
                    $grading[$user_ids[$i]][] = $user_ids[($i + $j) % $user_number];
                }
            }

            foreach ($grading as $grader => $assignment) {
                foreach ($assignment as $student) {
                    $this->core->getQueries()->insertPeerGradingAssignment($grader, $student, $gradeable->getId());
                }
            }
        }
        //}
    }

    private function newComponent(Gradeable $gradeable) {
        return new Component($this->core, $gradeable, [
            'id' => 0,
            'title' => '',
            'ta_comment' => '',
            'student_comment' => '',
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => 0,
            'upper_clamp' => 0,
            'text' => false,
            'peer_component' => false,
            'order' => -1,
            'page' => Component::PDF_PAGE_NONE,
            'is_itempool_linked' => false,
            'itempool' => ""
        ]);
    }

    private function newMark(Component $component) {
        return new Mark($this->core, $component, [
            'id' => 0,
            'points' => 0,
            'title' => '',
            'order' => 0,
            'publish' => false
        ]);
    }

    // Generates a blank first component for a gradeable
    private function genBlankComponent(Gradeable $gradeable) {
        // Make a new gradeable component with good default values
        $component = $this->newComponent($gradeable);
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            //Add a new mark (count up)
            $mark0 = $this->newMark($component);
            $mark0->setTitle('No Credit');
            $component->setMarks([$mark0]);
        }
        elseif ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $component->setTitle('Checkpoint 1');
            $component->setPoints(['lower_clamp' => 0, 'default' => 0, 'max_value' => 1, 'upper_clamp' => 1]);
        }
        elseif ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            // Add a new mark to the db if its electronic
            $mark = $this->newMark($component);
            $component->setMarks([$mark]);
        }
        else {
            throw new \InvalidArgumentException('Gradeable type invalid');
        }

        // Add it to the gradeable
        $gradeable->setComponents([$component]);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/rubric", methods: ["POST"])]
    public function updateRubricRequest($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        try {
            $this->updateRubric($gradeable, $_POST['values']);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    // Parses the checkpoint details from the user form into a Component.  NOTE: order is not set here
    private static function parseCheckpoint(Component $component, $details) {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        if (!isset($details['extra_credit'])) {
            $details['extra_credit'] = 'false';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            // if it is extra credit then it would be out of 0 points otherwise 1
            'max_value' => $details['extra_credit'] === 'true' ? 0 : 1,
            'upper_clamp' => 1
        ]);
        $component->setText(false);
        $component->setPeerComponent(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    private static function parseNumeric(Component $component, $details) {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        if (!isset($details['max_score'])) {
            $details['max_score'] = 0;
        }
        if (!isset($details['extra_credit'])) {
            $details['extra_credit'] = 'false';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => $details['extra_credit'] === 'true' ? 0 : $details['max_score'],
            'upper_clamp' => $details['max_score']
        ]);
        $component->setText(false);
        $component->setPeerComponent(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    private static function parseText(Component $component, $details) {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => 0,
            'upper_clamp' => 0
        ]);
        $component->setText(true);
        $component->setPeerComponent(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    /**
     * Returns true if given path has a file named config.json in it, false otherwise
     * @param string $folder_path
     * @return boolean
     */
    private function checkPathToConfigFile($folder_path) {
        if (!file_exists($folder_path)) {
            return false;
        }
        try {
            $file_iter = new \RecursiveDirectoryIterator($folder_path, \RecursiveDirectoryIterator::SKIP_DOTS);
        }
        catch (\Exception $e) {
            return false;
        }
        while ($file_iter->valid()) {
            if ($file_iter->current()->getFilename() == 'config.json') {
                return true;
            }
            $file_iter->next();
        }
        return false;
    }

    /**
     * Iterates through the directory and finds config.json files (BFS)
     * Terminates loop after a hard coded number of folders are searched (currently 1000)
     * does not look at files deeper than the config.json file
     * pushes new errors to $error_messages if something goes wrong
     * returns array of valid paths arrays of form [path_name,full_path]
     * @param array $dir_queue
     * @param array $error_messages
     * @param integer $repo_id_number
     * @return array
     */
    private function getValidPathsToConfigDirectories($dir_queue, &$error_messages, $repo_id_number) {
        $repository_path = $dir_queue[0];
        $count = 0;
        $return_array = [];

        while (count($dir_queue) != 0) {
            if ($count >= 1000) {
                $error_messages[] = "Repository #" . $repo_id_number . " entered on the \"Course Settings\" is too large to parse.";
                return [];
            }

            $dir = $dir_queue[0];
            unset($dir_queue[0]);
            $dir_queue = array_values($dir_queue);

            if (!file_exists($dir) || !is_dir($dir)) {
                $error_messages[] = "An error occurred when parsing repository #" . $repo_id_number . " entered on the \"Course Settings\" page";
                return [];
            }

            try {
                $iter = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
            }
            catch (\Exception $e) {
                $error_messages[] = "An error occurred when parsing repository #" . $repo_id_number . " entered on the \"Course Settings\" page";
                return [];
            }

            if ($this->checkPathToConfigFile($dir)) {
                $return_array[] = ["DIRECTORY " . $repo_id_number . ": " . substr($dir, strlen($repository_path)),$dir];
            }
            else {
                while ($iter->valid()) {
                    $file = $iter->current();
                    if ($file->isDir()) {
                        $dir_queue[] = $file->getPathname();
                    }
                    $iter->next();
                }
            }
            $count++;
        }
        return $return_array;
    }

    private function updateRubric(Gradeable $gradeable, $details) {
        if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            if (!isset($details['checkpoints'])) {
                $details['checkpoints'] = [];
            }
            if (!isset($details['text'])) {
                $details['text'] = [];
            }

            $checkpoint_labels = [];
            $checkpoint_extra = [];
            $text_labels = [];

            $num_checkpoints = count($details['checkpoints']);
            $num_text = count($details['text']);
            $start_index_text = 0;

            $old_checkpoints = [];
            $num_old_checkpoints = 0;
            $old_texts = [];
            $num_old_texts = 0;

            foreach ($gradeable->getComponents() as $old_component) {
                if ($old_component->isText()) {
                    $old_texts[] = $old_component;
                    $num_old_texts++;
                    $text_labels[] = $old_component->getTitle();
                }
                else {
                    $old_checkpoints[] = $old_component;
                    $num_old_checkpoints++;
                    $checkpoint_labels[] = $old_component->getTitle();

                    // Check if this checkpoint is extra credit
                    $checkpoint_extra[] = $old_component->isExtraCredit();
                }
            }

            $form_json = [
                'checkpoint_label' => $checkpoint_labels,
                'checkpoint_extra' => $checkpoint_extra,
                'num_text_items' => $num_old_texts,
                'num_checkpoint_items' => $num_old_checkpoints,
                'text_label' => $text_labels,
            ];

            // Iterate through existing components
            $new_components = [];
            $x = 0;
            foreach ($old_checkpoints as $old_component) {
                if ($x < $num_checkpoints) {
                    self::parseCheckpoint($old_component, $details['checkpoints'][$x]);
                    $old_component->setOrder($x);
                    $new_components[] = $old_component;
                }
                $x++;
            }

            for ($x = $num_old_checkpoints; $x < $num_checkpoints; $x++) {
                $component = $this->newComponent($gradeable);
                self::parseCheckpoint($component, $details['checkpoints'][$x]);
                $component->setOrder($x);
                $new_components[] = $component;
            }

            // Update existing text components with new details if they exist
            $z = $x;
            $x = 0;
            foreach ($old_texts as $old_text) {
                if ($x < $num_text && $x < $num_old_texts) {
                    self::parseText($old_text, $details['text'][$x]);
                    $old_text->setOrder($z + $x);// Maintain correct order after checkpoints
                    $new_components[] = $old_text;
                    $start_index_text++;
                }
                $x++;
            }
            // Add new text components if number of text items increase
            for ($y = $start_index_text; $y < $num_text; $y++) {
                $component = $this->newComponent($gradeable);
                self::parseText($component, $details['text'][$y]);
                $component->setOrder($y + $z);
                $new_components[] = $component;
            }
        }
        elseif ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            if (!isset($details['numeric'])) {
                $details['numeric'] = [];
            }
            if (!isset($details['text'])) {
                $details['text'] = [];
            }

            $num_numeric = count($details['numeric']);
            $num_text = count($details['text']);

            $old_numerics = [];
            $num_old_numerics = 0;
            $old_texts = [];
            $num_old_texts = 0;

            foreach ($gradeable->getComponents() as $old_component) {
                if ($old_component->isText()) {
                    $old_texts[] = $old_component;
                    $num_old_texts++;
                }
                else {
                    $old_numerics[] = $old_component;
                    $num_old_numerics++;
                }
            }

            $new_components = [];
            $x = 0;
            foreach ($old_numerics as $old_numeric) {
                if ($x < $num_numeric && $x < $num_old_numerics) {
                    self::parseNumeric($old_numeric, $details['numeric'][$x]);
                    $old_numeric->setOrder($x);
                    $new_components[] = $old_numeric;
                }
                $x++;
            }

            for ($x = $num_old_numerics; $x < $num_numeric; $x++) {
                $component = $this->newComponent($gradeable);
                self::parseNumeric($component, $details['numeric'][$x]);
                $component->setOrder($x);
                $new_components[] = $component;
            }

            // Iterate through text components and update them
            $z = $x;
            $x = 0;
            foreach ($old_texts as $old_text) {
                if ($x < $num_text && $x < $num_old_texts) {
                    self::parseText($old_text, $details['text'][$x]);
                    $old_text->setOrder($z + $x);
                    $new_components[] = $old_text;
                }
                $x++;
            }

            // Add new text items if any
            for ($y = $num_old_texts; $y < $num_text; $y++) {
                $component = $this->newComponent($gradeable);
                self::parseText($component, $details['text'][$y]);
                $component->setOrder($y + $z);
                $new_components[] = $component;
            }
        }
        else {
            throw new \InvalidArgumentException("Invalid gradeable type");
        }
        // Finally, Set the components and update the gradeable
        $gradeable->setComponents($new_components);

        // Save to the database
        $this->core->getQueries()->updateGradeable($gradeable);
    }


    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/graders", methods: ["POST"])]
    public function updateGradersRequest($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        try {
            $this->updateGraders($gradeable, $_POST);
            // Finally, send the requester back the information
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail('Error setting graders' . $e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function updateGraders(Gradeable $gradeable, $details) {
        $new_graders = [];
        if (isset($details['graders'])) {
            $new_graders = $details['graders'];
        }

        $gradeable->setRotatingGraderSections($new_graders);
        $this->core->getQueries()->updateGradeable($gradeable);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable", methods: ["POST"])]
    public function createGradeableRequest() {
        $gradeable_id = $_POST['id'] ?? '';

        try {
            $build_result = $this->createGradeable($gradeable_id, $_POST);

            // Finally, redirect to the edit page
            if ($build_result !== null) {
                $this->core->addErrorMessage($build_result);
            }
            $this->redirectToEdit($gradeable_id);
        }
        catch (\Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            $this->core->redirect($this->core->buildCourseUrl());
        }
    }

    private function createGradeable($gradeable_id, $details) {
        // Make sure the gradeable doesn't already exist
        if ($this->core->getQueries()->existsGradeable($gradeable_id)) {
            throw new \InvalidArgumentException('Gradeable already exists');
        }

        $default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        // Create the gradeable with good default information
        //
        $gradeable_type = GradeableType::stringToType($details['type']);
        $gradeable_create_data = [
            'type' => $gradeable_type,
            'grader_assignment_method' => Gradeable::REGISTRATION_SECTION,
            'min_grading_group' => 1,
        ];

        $template_property_names = [
            'min_grading_group',
            'grader_assignment_method',
            'ta_instructions',
            'autograding_config_path',
            'student_view',
            'student_view_after_grades',
            'student_download',
            'student_submit',
            'late_days',
            'precision'
        ];
        // Make sure the template exists if we're using one
        $template_gradeable = null;
        if (array_key_exists('gradeable_template', $details) && $details['gradeable_template'] !== '--None--') {
            $template_id = $details['gradeable_template'];
            $template_gradeable = $this->core->getQueries()->getGradeableConfig($template_id);
            if ($template_gradeable === null) {
                throw new \InvalidArgumentException('Template gradeable does not exist');
            }

            // Setup the create data from the template
            $template_data = $template_gradeable->toArray();
            foreach ($template_property_names as $name) {
                $gradeable_create_data[$name] = $template_data[$name];
            }
        }
        else {
            $non_template_property_values = [
                'min_grading_group' => 1,
                'grader_assignment_method' => Gradeable::REGISTRATION_SECTION,
                'ta_instructions' => '',
                'autograding_config_path' =>
                    FileUtils::joinPaths($this->core->getConfig()->getSubmittyInstallPath(), 'more_autograding_examples/upload_only/config'),
                'student_view' => true,
                'student_view_after_grades' => false,
                'student_download' => true,
                'student_submit' => true,
                'late_days' => $details['late_days'] ?? $default_late_days,
                'precision' => 0.5
            ];
            $gradeable_create_data = array_merge($gradeable_create_data, $non_template_property_values);
        }

        // first-page properties
        $front_page_property_names = [
            'id',
            'title',
            'instructions_url',
            'syllabus_bucket'
        ];
        foreach ($front_page_property_names as $prop) {
            $gradeable_create_data[$prop] = $details[$prop] ?? '';
        }

        if (!in_array($details['syllabus_bucket'], self::syllabus_buckets, true)) {
            throw new \InvalidArgumentException('Syllabus bucket must be one of the following: ' . implode(', ', self::syllabus_buckets));
        }

        $repo_name = '';
        $subdir = '';
        $using_subdirectory = false;
        if (Utils::getBooleanValue($details['using_subdirectory'])) {
            $subdir = $details['vcs_subdirectory'];
            $using_subdirectory = true;
        }
        $vcs_partial_path = '';
        // VCS specific values
        if (Utils::getBooleanValue($details['vcs'])) {
            $host_button = $details['vcs_radio_buttons'];
            $host_type = -1;
            // Find which radio button is pressed and what host type to use
            if ($host_button === 'submitty-hosted') {
                $host_type = 0;
                $repo_name = $details['id'];
                $vcs_partial_path = $details['id'] . (Utils::getBooleanValue($details['team_assignment']) ? "/{\$team_id}" : "/{\$user_id}");
            }
            elseif ($host_button === 'submitty-hosted-url') {
                $host_type = 1;
                $repo_name = $details['vcs_path'];
                $vcs_partial_path = $details['vcs_path'] . "/{\$user_id}";
            }
            elseif ($host_button === 'public-github') {
                $host_type = 2;
            }
            elseif ($host_button === 'private-github') {
                $host_type = 3;
            }
            elseif ($host_button === 'self-hosted') {
                $host_type = 4;
                $vcs_partial_path = $details['external_repo'];
            }

            $vcs_property_values = [
                'vcs' => true,
                'vcs_subdirectory' => $subdir,
                'using_subdirectory' => $using_subdirectory,
                'vcs_host_type' => $host_type,
                'vcs_partial_path' => $vcs_partial_path
            ];
            $gradeable_create_data = array_merge($gradeable_create_data, $vcs_property_values);
        }
        else {
            $non_vcs_property_values = [
                'vcs' => false,
                'vcs_subdirectory' => $subdir,
                'using_subdirectory' => $using_subdirectory,
                'vcs_host_type' => GradeableUtils::VCS_TYPE_NONE,
                'vcs_partial_path' => $vcs_partial_path
            ];
            $gradeable_create_data = array_merge($gradeable_create_data, $non_vcs_property_values);
        }

        // Electronic-only values
        if ($gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $jsonThreads = json_encode('{}');
            $discussion_clicked = Utils::getBooleanValue($details['discussion_based'] ?? false);

            //Validate user input for discussion threads
            if ($discussion_clicked) {
                $jsonThreads = array_map('intval', explode(',', $details['discussion_thread_id']));
                foreach ($jsonThreads as $thread) {
                    if (!$this->core->getQueries()->existsThread($thread)) {
                        throw new \InvalidArgumentException('Invalid thread id specified.');
                    }
                }
                $jsonThreads = json_encode($jsonThreads);
            }

            $grade_inquiry_allowed = Utils::getBooleanValue($details['grade_inquiry_allowed'] ?? false);
            $grade_inquiry = Utils::getBooleanValue($details['grade_inquiry_per_component_allowed'] ?? false);
            $autograding_config_path = $details['autograding_config_path'] ?? FileUtils::joinPaths($this->core->getConfig()->getSubmittyInstallPath(), 'more_autograding_examples/upload_only/config');
            $gradeable_create_data = array_merge($gradeable_create_data, [
                'team_assignment' => Utils::getBooleanValue($details['team_assignment']),
                'ta_grading' => Utils::getBooleanValue($details['ta_grading']),
                'team_size_max' => $details['team_size_max'],
                'grade_inquiry_allowed' => $grade_inquiry_allowed,
                'grade_inquiry_per_component_allowed' => $grade_inquiry,
                'autograding_config_path' => $autograding_config_path,
                'allow_custom_marks' => true,
                //For discussion component
                'discussion_based' => $discussion_clicked,
                'discussion_thread_ids' => $jsonThreads,

                // TODO: properties that aren't supported yet
                'peer_grading' => false,
                'peer_grade_set' => 0,
                'late_submission_allowed' => true,
                'hidden_files' => "",
                'instructor_blind' => 1,
                'limited_access_blind' => 1,
                'peer_blind' => 3,
                'depends_on' => null,
                'depends_on_points' => null,
                'has_due_date' => $details['has_due_date'] ?? true,
                'has_release_date' => $details['has_release_date'] ?? true
            ]);
        }
        else {
            // Values for these electronic-only properties
            $gradeable_create_data = array_merge($gradeable_create_data, [
                'team_assignment' => false,
                'vcs' => false,
                'team_size_max' => 0,
                'vcs_subdirectory' => '',
                'using_subdirectory' => false,
                'vcs_partial_path' => '',
                'vcs_host_type' => GradeableUtils::VCS_TYPE_NONE,
                'autograding_config_path' => '',
                'peer_grading' => false,
                'peer_grade_set' => 0,
                'late_submission_allowed' => $details['late_submission_allowed'] ?? true,
                'hidden_files' => ""
            ]);
        }

        // Setup good default dates
        $tonight = $this->core->getDateTimeNow();
        $tonight->setTime(23, 59, 59);
        if ($tonight->diff($this->core->getDateTimeNow())->h < 12) {
            $tonight->add(new \DateInterval('P1D'));
        }
        $date_names = [
            'ta_view_start_date' => '',
            'grade_start_date' => 'P10D',
            'grade_due_date' => 'P14D',
            'grade_released_date' => 'P14D',
            'team_lock_date' => 'P7D',
            'submission_open_date' => '',
            'submission_due_date' => 'P7D',
            'grade_inquiry_start_date' => 'P15D',
            'grade_inquiry_due_date' => 'P21D'
        ];

        foreach ($date_names as $time_string => $tonight_modifier) {
            $gradeable_create_data = array_merge(
                $gradeable_create_data,
                [
                    $time_string => $this->getDateTimeForGradeable(
                        $details[$time_string] ?? '',
                        $tonight,
                        $tonight_modifier
                    )
                ]
            );
        }

        // Finally, construct the gradeable
        $gradeable = new Gradeable($this->core, $gradeable_create_data);

        // Setup student permissions specially for scanned exams
        if (Utils::getBooleanValue($details['bulk_upload'])) {
            $gradeable->setStudentView(true);
            $gradeable->setStudentViewAfterGrades(true);
            $gradeable->setStudentSubmit(false);
            $gradeable->setStudentDownload(false);

            $gradeable->setAutogradingConfigPath(
                FileUtils::joinPaths($this->core->getConfig()->getSubmittyInstallPath(), 'more_autograding_examples/pdf_exam/config')
            );
        }

        // Generate a blank component to make the rubric UI work properly
        $this->genBlankComponent($gradeable);

        // Save the gradeable to the database
        $this->core->getQueries()->createGradeable($gradeable); // creates the gradeable

        // start the build
        $build_status = $this->enqueueBuild($gradeable);

        if (
            $build_status == null
            && $gradeable->isVcs()
            && ($gradeable->getVcsHostType() === 0 || $gradeable->getVcsHostType() === 1)
            && !$gradeable->isTeamAssignment()
        ) {
            $this->enqueueGenerateRepos(
                $this->core->getConfig()->getTerm(),
                $this->core->getConfig()->getCourse(),
                $repo_name,
                $subdir
            );
        }

        return $build_status;
    }

    public function getDateTimeForGradeable(string $time_string, \DateTime $tonight, string $tonight_modifier = ''): \DateTime {
        if ($time_string !== '') {
            return $this->core->getDateTimeSpecific($time_string);
        }
        if ($tonight_modifier !== '') {
            return (clone $tonight)->add(new \DateInterval($tonight_modifier));
        }
        else {
            return (clone $tonight);
        }
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/update", methods: ["POST"])]
    public function updateGradeableRequest($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        unset($_POST['csrf_token']);
        try {
            $response_props = $this->updateGradeable($gradeable, $_POST);
            // Finally, send the requester back the information
            $this->core->getOutput()->renderJsonSuccess($response_props);
        }
        catch (ValidationException $e) {
            $this->core->getOutput()->renderJsonFail('See "data" for details', $e->getDetails());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function updateGradeable(Gradeable $gradeable, $details) {
        $errors = [];
        // Implicitly updated properties to tell the client about
        $updated_properties = [];

        // If the post array is 0, that means that the name of the element was blank
        if (count($details) === 0) {
            throw new \InvalidArgumentException('Request contained no properties, perhaps the name was blank?');
        }
        // Trigger a rebuild if the config changes
        $trigger_rebuild_props = ['autograding_config_path', 'vcs_subdirectory'];
        $trigger_rebuild = count(array_intersect($trigger_rebuild_props, array_keys($details))) > 0;

        $boolean_properties = [
            'ta_grading',
            'student_view',
            'student_view_after_grades',
            'student_download',
            'student_submit',
            'peer_grading',
            'late_submission_allowed',
            'grade_inquiry_allowed',
            'grade_inquiry_per_component_allowed',
            'discussion_based',
            'vcs',
            'using_subdirectory',
            'has_due_date',
            'has_release_date',
            'allow_custom_marks'
        ];

        $discussion_ids = 'discussion_thread_id';

        $numeric_properties = [
            'precision',
            'grader_assignment_method',
            'depends_on_points',
            'notifications_sent'
        ];
        // Date properties all need to be set at once
        $dates = $gradeable->getDates();

        $date_set = false;
        foreach (array_merge(Gradeable::date_properties, ['late_days']) as $date_property) {
            if (isset($details[$date_property])) {
                $dates[$date_property] = $details[$date_property];

                if ($date_property === 'late_days') {
                    if (!is_numeric($dates[$date_property])) {
                        $errors[$date_property] = 'Late days must be a number';
                    }
                    elseif (intval($dates[$date_property]) < 0) {
                        $errors[$date_property] = 'Late days must be a positive number';
                    }
                }
                elseif ($dates[$date_property] > DateUtils::MAX_TIME) {
                    $errors[$date_property] = Gradeable::date_display_names[$date_property] . ' Date is higher than the max allowed date! (' . DateUtils::MAX_TIME . ')';
                }

                // Unset dates so we don't try and use it in the other loop
                unset($details[$date_property]);
                $date_set = true;
            }
        }

        // TO DO: Update late day cache for admin late day update
        // TO DO: Update late day cache for admin gradeable due date update
        $late_day_status = null;

        // Set default value which may be set in loop below
        $grade_inquiry_modified = false;

        // Apply other new values for all properties submitted
        foreach ($details as $prop => $post_val) {
            // Convert boolean values into booleans
            if (in_array($prop, $boolean_properties, true)) {
                $post_val = $post_val === 'true';
            }

            if (in_array($prop, $numeric_properties, true) && !is_numeric($post_val)) {
                $errors[$prop] = "{$prop} must be a number";
                continue;
            }

            if ($prop === "depends_on") {
                try {
                    $temp_gradeable = $this->tryGetGradeable($post_val, false);
                    if ($temp_gradeable == false) {
                        $post_val = null;
                    }
                    else {
                        $depends_on_points = $details['depends_on_points'];
                        if ($depends_on_points == null) {
                            $depends_on_points = $gradeable->getDependsOnPoints();
                        }
                        if ($depends_on_points == null) {
                            $gradeable->setDependsOnPoints(0);
                        }
                        else {
                            if ($depends_on_points < 0 || ($depends_on_points > $temp_gradeable->getDependsOnPoints() && $temp_gradeable->getDependsOnPoints() !== null)) {
                                $errors['depends_on_points'] = "Invalid depends on points!";
                            }
                        }
                    }
                }
                catch (\Exception $e) {
                    $post_val = null;
                    $errors[$prop] = $e->getMessage();
                }
            }
            // Converts string array sep by ',' to json
            if ($prop === $discussion_ids) {
                $post_val = array_map('intval', explode(',', $post_val));
                foreach ($post_val as $thread) {
                    if (!$this->core->getQueries()->existsThread($thread)) {
                        $errors[$prop] = 'Invalid thread id specified.';
                        break;
                    }
                }
                if (count($errors) == 0) {
                    $post_val = json_encode($post_val);
                }
                else {
                    continue;
                }
            }

            if ($prop === 'grade_inquiry_allowed') {
                if ($post_val !== $gradeable->isGradeInquiryAllowed()) {
                    $grade_inquiry_modified = true;
                }
            }

            if ($prop === 'grade_inquiry_per_component_allowed' && $post_val === false && $gradeable->isGradeInquiryPerComponentAllowed()) {
                $this->core->getQueries()->convertInquiryComponentId($gradeable);
            }

            if ($prop === 'grade_inquiry_per_component_allowed' && $post_val === true && !$gradeable->isGradeInquiryPerComponentAllowed()) {
                $this->core->getQueries()->revertInquiryComponentId($gradeable);
            }

            if ($prop === 'notifications_sent' && $post_val === "0" && $gradeable->getNotificationsSent() > 0) {
                $this->core->getQueries()->resetGradeableNotifications($gradeable);
            }

            if ($prop === 'syllabus_bucket' && !in_array($post_val, self::syllabus_buckets, true)) {
                $errors['syllabus_bucket'] = 'Syllabus bucket must be one of the following: ' . implode(', ', self::syllabus_buckets);
            }

            // Try to set the property
            try {
                //convert the property name to a setter name
                $setter_name = 'set' . implode(
                    '',
                    array_map(function ($val) {
                        return ucfirst($val);
                    },
                    explode('_', $prop))
                );
                $gradeable->$setter_name($post_val);
            }
            catch (\Exception $e) {
                // If something goes wrong, record it so we can tell the user
                $errors[$prop] = $e->getMessage();
            }
        }

        if (!$gradeable->hasDueDate() && $gradeable->hasReleaseDate()) {
            $gradeable->setHasReleaseDate(false);
        }

        // Set the dates last just in case the request contained parameters that
        //  affect date validation
        if ($date_set) {
            try {
                $gradeable->setDates($dates, $grade_inquiry_modified);
                $updated_properties = $gradeable->getDateStrings(false);
            }
            catch (ValidationException $e) {
                $errors = array_merge($errors, $e->getDetails());
            }
        }

        if ($trigger_rebuild) {
            $result = $this->enqueueBuild($gradeable);
            if ($result !== null) {
                // TODO: what key should this get?
                $errors['server'] = $result;
            }
            else {
                $updated_properties[] = 'rebuild_queued';
            }
        }

        // Be strict.  Only apply database changes if there were no errors
        if (count($errors) !== 0) {
            throw new ValidationException('', $errors);
        }
        $this->core->getQueries()->updateGradeable($gradeable);
        // Only return updated properties if the changes were applied
        return $updated_properties;
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/delete", methods: ["POST"])]
    public function deleteGradeable($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable == false) {
            $this->core->addErrorMessage("Invalid gradeable id");
            $this->core->redirect($this->core->buildCourseUrl());
        }
        if (!$gradeable->canDelete()) {
            $this->core->addErrorMessage("Gradeable " . $gradeable_id . " cannot be deleted.");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $this->core->getQueries()->deleteGradeable($gradeable_id);

        $course_path = $this->core->getConfig()->getCoursePath();

        $file = FileUtils::joinPaths($course_path, "config", "form", "form_" . $gradeable_id . ".json");
        if ((file_exists($file)) && (!unlink($file))) {
            die("Cannot delete form_{$gradeable_id}.json");
        }

        // this will cleanup the build files
        $this->enqueueBuildFile($gradeable_id);

        $this->core->redirect($this->core->buildCourseUrl());
    }

    private function writeFormConfig(Gradeable $gradeable) {
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            return null;
        }

        // Refresh the configuration file with updated information
        // See 'make_assignments_txt_file.py' and grade_item.py for where these properties are used
        // Note: These property names must match the 'setup_sample_courses.py' names
        $jsonProperties = [
            'gradeable_id' => $gradeable->getId(),
            'config_path' => $gradeable->getAutogradingConfigPath(),
            'date_due' => $gradeable->hasDueDate() ? DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()) : null,
            'upload_type' => $gradeable->isVcs() ? "repository" : "upload file",
            'subdirectory' => $gradeable->getVcsSubdirectory(),
            'using_subdirectory' => $gradeable->isUsingSubdirectory(),
            'vcs_partial_path' => $gradeable->getVcsPartialPath(),
        ];

        $fp = $this->core->getConfig()->getCoursePath() . '/config/form/form_' . $gradeable->getId() . '.json';
        if (
            (!is_writable($fp) && file_exists($fp))
            || file_put_contents($fp, json_encode($jsonProperties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false
        ) {
            return "Failed to write to file {$fp}";
        }
        return null;
    }

    private function enqueueBuildFile($g_id) {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        // FIXME:  should use a variable instead of hardcoded top level path
        $config_build_file = "/var/local/submitty/daemon_job_queue/" . $semester . "__" . $course . "__" . $g_id . ".json";

        $config_build_data = [
            "job" => "BuildConfig",
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $g_id
        ];

        if (
            (!is_writable($config_build_file) && file_exists($config_build_file))
            || file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false
        ) {
            return "Failed to write to file {$config_build_file}";
        }
        return null;
    }

    public static function enqueueGenerateRepos(string $semester, string $course, string $g_id, string $subdirectory) {
        // FIXME:  should use a variable instead of hardcoded top level path
        $config_build_file = "/var/local/submitty/daemon_job_queue/generate_repos__" . $semester . "__" . $course . "__" . $g_id . ".json";

        $config_build_data = [
            "job" => "RunGenerateRepos",
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $g_id,
            "subdirectory" => $subdirectory
        ];

        if (
            (!is_writable($config_build_file) && file_exists($config_build_file))
            || file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false
        ) {
            return "Failed to write to file {$config_build_file}";
        }
        return null;
    }

    public function enqueueBuild(Gradeable $gradeable) {
        // If write form config fails, it will return non-null and end execution, but
        //  if it does return null, we want to run 'enqueueBuildFile'.  This coalescing can
        //  be chained so long as 'null' is the success condition.
        return $this->writeFormConfig($gradeable) ?? $this->enqueueBuildFile($gradeable->getId());
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/rebuild")]
    public function rebuildGradeableRequest($gradeable_id) {
        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $result = $this->enqueueBuild($gradeable);
        if ($result !== null) {
            die($result);
        }
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/build_log", methods: ["GET"])]
    public function getBuildLogs(string $gradeable_id): JsonResponse {
        $build_script_output_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'build', $gradeable_id, 'build_script_output.txt');
        $build_script_output = is_file($build_script_output_file) ? htmlentities(file_get_contents($build_script_output_file)) : null;
        $cmake_out_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'build', $gradeable_id, 'log_cmake_output.txt');
        $cmake_output = is_file($cmake_out_dir) ? htmlentities(file_get_contents($cmake_out_dir)) : null;

        return JsonResponse::getSuccessResponse([$build_script_output,$cmake_output]);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/build_status", methods: ["GET"])]
    public function getBuildStatusOfGradeable(string $gradeable_id): void {
        $queued_filename = $this->core->getConfig()->getTerm() . '__' . $this->core->getConfig()->getCourse() . '__' . $gradeable_id . '.json';
        $rebuilding_filename = 'PROCESSING_' . $this->core->getConfig()->getTerm() . '__' . $this->core->getConfig()->getCourse() . '__' . $gradeable_id . '.json';
        $queued_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), 'daemon_job_queue', $queued_filename);
        $rebuilding_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), 'daemon_job_queue', $rebuilding_filename);

        if (is_file($rebuilding_path)) {
            $status = 'processing';
        }
        elseif (is_file($queued_path)) {
            $status = 'queued';
        }
        else {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
            $status = $gradeable->hasAutogradingConfig();

            // Check for schema validation errors and return a different status if needed.
            if ($status) {
                $logs = $this->getBuildLogs($gradeable_id);

                $needle = 'The submitty configuration validator detected the above error in your config.';
                $haystack = $logs->json['data'][0] ?? '';

                if (str_contains($haystack, 'MAKE ERROR')) {
                    $status = false;
                }
                elseif (str_contains($haystack, $needle)) {
                    $status = 'warnings';
                }
                elseif (str_contains($haystack, 'WARNING:')) {
                    $status = 'warnings';
                }
            }
        }
        clearstatcache();
        $this->core->getOutput()->renderJsonSuccess($status);
    }

    /**
     * Shifts all dates in the array up to and including $date_prop to be no later than $time
     * @param array $dates
     * @param string $date_prop
     * @param \DateTime $time
     */
    private function shiftDates(array &$dates, string $date_prop, \DateTime $time) {
        foreach (Gradeable::date_validated_properties as $d) {
            if ($dates[$d] > $time) {
                $dates[$d] = $time;
            }
            if ($date_prop === $d) {
                break;
            }
        }
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/quick_link")]
    public function openquickLink($gradeable_id, $action) {
        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $dates = $gradeable->getDates();
        $now = $this->core->getDateTimeNow();
        $message = "";
        $success = null;
        //what happens on the quick link depends on the action
        if ($action === "release_grades_now") {
            if ($gradeable->hasReleaseDate()) {
                if ($dates['grade_released_date'] > $now) {
                    $this->shiftDates($dates, 'grade_released_date', $now);
                    $message .= "Released grades for ";
                    $success = true;
                }
                else {
                    $message .= "Grades already released for ";
                    $success = false;
                }
            }
            else {
                $message .= "Can't release grades for ";
                $success = false;
            }
        }
        elseif ($action === "open_ta_now") {
            if ($dates['ta_view_start_date'] > $now) {
                $this->shiftDates($dates, 'ta_view_start_date', $now);
                $message .= "Opened TA access to ";
                $success = true;
            }
            else {
                $message .= "TA access already open for ";
                $success = false;
            }
        }
        elseif ($action === "open_grading_now") {
            if ($dates['grade_start_date'] > $now) {
                $this->shiftDates($dates, 'grade_start_date', $now);
                $message .= "Opened grading for ";
                $success = true;
            }
            else {
                $message .= "Grading already open for ";
                $success = false;
            }
        }
        elseif ($action === "open_students_now") {
            if ($dates['submission_open_date'] > $now) {
                $this->shiftDates($dates, 'submission_open_date', $now);
                $message .= "Opened student access to ";
                $success = true;
            }
            else {
                $message .= "Student access already open for ";
                $success = false;
            }
        }
        elseif ($action === "close_submissions") {
            if ($gradeable->hasDueDate()) {
                if ($dates['submission_due_date'] > $now) {
                    $this->shiftDates($dates, 'submission_due_date', $now);
                    $message .= "Closed assignment ";
                    $success = true;
                }
                else {
                    $message .= "Grading already closed for ";
                    $success = false;
                }
            }
            else {
                $message .= "Can't close submissions for ";
                $success = false;
            }
        }
        $gradeable->setDates($dates);
        $this->core->getQueries()->updateGradeable($gradeable);
        if ($success === true) {
            $this->core->addSuccessMessage($message . $gradeable_id);
        }
        elseif ($success === false) {
            $this->core->addErrorMessage($message . $gradeable_id);
        }
        else {
            $this->core->addErrorMessage("Failed to update status of " . $gradeable_id);
        }

        $this->core->redirect($this->core->buildCourseUrl());
    }

    private function redirectToEdit($gradeable_id) {
        $url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'update']) . '?' . http_build_query(['nav_tab' => '-1']);
        header('Location: ' . $url);
    }

    /**
     * Exports components to json and downloads for user
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/export")]
    public function exportComponentsRequest($gradeable_id) {
        $url = $this->core->buildCourseUrl();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Invalid gradeable id");
            $this->core->redirect($url);
        }

        // Permission checks
        if (!$this->core->getAccess()->canI("grading.electronic.export_components", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("Insufficient permissions to export components");
            $this->core->redirect($url);
        }

        try {
            $arrs = $gradeable->exportComponents();
            $this->core->getOutput()->renderFile(json_encode($arrs, JSON_PRETTY_PRINT), $gradeable->getId() . '_components.json');
        }
        catch (\Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            $this->core->redirect($url);
        }
    }

    /**
     * Imports components from uploaded files into gradeable (single-depth array)
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/import", methods: ["POST"])]
    public function importComponents($gradeable_id) {
        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Permission checks
        if (!$this->core->getAccess()->canI("grading.electronic.add_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail("Insufficient permissions to import components");
            return;
        }

        try {
            // decode file to array
            foreach ($_FILES as $f) {
                $comp_arrs = json_decode(file_get_contents($f['tmp_name']), true);
                foreach ($comp_arrs as $comp_arr) {
                    $gradeable->importComponent($comp_arr);
                }
            }

            // Save to the database
            $this->core->getQueries()->updateGradeable($gradeable);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/max_points")]
    public function maxPoints($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable !== false) {
            $autogradingConfig = $gradeable->getAutogradingConfig();
            $points = $autogradingConfig->getTotalHiddenNonExtraCredit() + $autogradingConfig->getTotalNonHidden();
            $this->core->getOutput()->renderJsonSuccess($points);
            return;
        }
        $this->core->getOutput()->renderJsonError("Unknown gradeable");
    }

    /**
     * Loads config info for a gradeable to allow editing
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/edit/load", methods: ["POST"])]
    public function loadConfigEditor(): void {
        $gradeable = $this->tryGetGradeable($_POST['gradeable_id']);

        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail("Invalid gradeable");
            return;
        }

        if (!$this->core->getAccess()->canI("grading.electronic.load_config", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail("Insufficient permissions to load content.");
            return;
        }

        $file_path = $_POST['file_path'];
        if (!FileUtils::validPath($file_path) || !str_starts_with($file_path, $gradeable->getAutogradingConfigPath())) {
            $this->core->getOutput()->renderJsonFail("Invalid file path");
            return;
        }

        $config_content = file_get_contents(FileUtils::joinPaths($file_path));
        $output = [];
        $output["config_content"] = $config_content;
        $this->core->getOutput()->renderJsonSuccess($output);
    }

    /**
     * Saves config info from a gradeable edit
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/edit/save", methods: ["POST"])]
    public function saveConfigEdit(): void {
        $gradeable = $this->tryGetGradeable($_POST['gradeable_id']);
        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail("Invalid gradeable");
            return;
        }

        if (!$gradeable->isUsingUploadedConfig()) {
            $this->core->getOutput()->renderJsonFail("You may only save changes to uploaded autograding configurations for the current course and semester.");
            return;
        }

        if (!$this->core->getAccess()->canI("grading.electronic.load_config", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail("Insufficient permissions to save changes.");
            return;
        }

        $file_path = $_POST['file_path'];
        if (!FileUtils::validPath($file_path) || !str_starts_with($file_path, $gradeable->getAutogradingConfigPath())) {
            $this->core->getOutput()->renderJsonFail("Invalid file path");
            return;
        }

        $write_success = FileUtils::writeFile($file_path, $_POST['write_content']);
        if (!$write_success) {
            $this->core->getOutput()->renderJsonFail("An error occurred writing the file.");
            return;
        }

        $result = $this->enqueueBuild($gradeable);
        if ($result !== null) {
            $this->core->getOutput()->renderJsonFail("An error occurred queuing the gradeable for rebuild.");
        }

        $this->core->getOutput()->renderJsonSuccess();
    }

    /**
     * Updates the saved config structure when the user adds or deletes.
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/edit/modify_structure", methods: ["POST"])]
    public function modifyStructure(): void {
        $gradeable     = $this->tryGetGradeable($_POST['gradeable_id']);
        $action        = $_POST['action'];
        $incoming_path = $_POST['path'] ?? '';

        // Permissions checks omitted for brevity

        $base_path = $gradeable->getAutogradingConfigPath();

        if ($action === 'delete') {
            // ── NORMALIZE to a relative path inside $base_path ──
            if (str_starts_with($incoming_path, $base_path)) {
                $relative_path = ltrim(substr($incoming_path, strlen($base_path)), '/');
            }
            else {
                $relative_path = ltrim($incoming_path, '/');
            }

            // ── REBUILD the full path ──
            $full_path = FileUtils::joinPaths($base_path, $relative_path);

            // Validation
            if (!FileUtils::validPath($full_path) || !str_starts_with($full_path, $base_path)) {
                $this->core->getOutput()->renderJsonFail("Invalid path: $incoming_path");
                return;
            }

            // Delete
            if (is_dir($full_path)) {
                FileUtils::recursiveRmdir($full_path);
            }
            else {
                unlink($full_path);
            }

            // Enqueue & respond
            $result = $this->enqueueBuild($gradeable);
            if ($result !== null) {
                $this->core->getOutput()->renderJsonFail("An error occurred queuing the gradeable for rebuild.");
                return;
            }
            $this->core->getOutput()->renderJsonSuccess("Deleted.");
            return;
        }

        // ── PREPARE for add_folder / add_file ──
        $relative_path = ltrim($incoming_path, '/');
        $full_path     = FileUtils::joinPaths($base_path, $relative_path);

        // Validation
        if (!FileUtils::validPath($full_path) || !str_starts_with($full_path, $base_path)) {
            $this->core->getOutput()->renderJsonFail("Invalid path.");
            return;
        }

        switch ($action) {
            case 'add_folder':
                if (!FileUtils::createDir($full_path)) {
                    $this->core->getOutput()->renderJsonFail("Could not create folder.");
                    return;
                }
                $result = $this->enqueueBuild($gradeable);
                if ($result !== null) {
                    $this->core->getOutput()->renderJsonFail("An error occurred queuing the gradeable for rebuild.");
                    return;
                }
                $this->core->getOutput()->renderJsonSuccess("Folder added.");
                return;

            case 'add_file':
                if (!isset($_FILES['file']['tmp_name']) || $_FILES['file']['tmp_name'] === '') {
                    $this->core->getOutput()->renderJsonFail("No file uploaded.");
                    return;
                }
                $content = file_get_contents($_FILES['file']['tmp_name']);
                if ($content === false || !FileUtils::writeFile($full_path, $content)) {
                    $this->core->getOutput()->renderJsonFail("Could not create file.");
                    return;
                }
                $result = $this->enqueueBuild($gradeable);
                if ($result !== null) {
                    $this->core->getOutput()->renderJsonFail("An error occurred queuing the gradeable for rebuild.");
                    return;
                }
                $this->core->getOutput()->renderJsonSuccess("File added.");
                return;
        }

        $this->core->getOutput()->renderJsonFail("Unknown action.");
    }
}
