<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\routers\AccessControl;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\Mark;
use app\models\User;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ComponentGradingController extends AbstractController {
    /**
     * Route for fetching a gradeable's rubric information
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/rubric", methods: ["GET"])]
    public function ajaxGetGradeableRubric($gradeable_id) {
        $grader = $this->core->getUser();
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get gradeable rubric data');
            return;
        }
        try {
            $results = $this->getGradeableRubric($gradeable, $grader);
            $this->core->getOutput()->renderJsonSuccess($results);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function getGradeableRubric(Gradeable $gradeable, User $grader) {
        $return = [
            'id' => $gradeable->getId(),
            'precision' => $gradeable->getPrecision()
        ];
        $return['components'] = array_map(function (Component $component) {
            return $component->toArray();
        }, array_filter($gradeable->getComponents(), function (Component $component) use ($gradeable) {
            return $this->core->getAccess()->canI('grading.electronic.view_component', ['gradeable' => $gradeable, 'component' => $component]);
        }));
        $return['components'] = array_values($return['components']);
        return $return;
    }

    /**
     * Gets a component and all of its marks
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components")]
    public function ajaxGetComponent($gradeable_id, $component_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $this->core->getUser()->getId(), false);
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }
        if (!$this->core->getAccess()->canI("grading.electronic.view_component", ["gradeable" => $gradeable, "component" => $component])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get component');
            return;
        }
        try {
            $this->core->getOutput()->renderJsonSuccess($component->toArray());
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    /**
     * Route for saving a component's properties (not its marks)
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/save", methods: ["POST"])]
    public function ajaxSaveComponent($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $ta_comment = $_POST['ta_comment'] ?? '';
        $student_comment = $_POST['student_comment'] ?? '';
        $lower_clamp = $_POST['lower_clamp'] ?? null;
        $default = $_POST['default'] ?? null;
        $max_value = $_POST['max_value'] ?? null;
        $upper_clamp = $_POST['upper_clamp'] ?? null;
        $is_itempool_linked = $_POST['is_itempool_linked'] ?? false;
        $itempool_option = $_POST['itempool_option'] ?? null;
        $page = $_POST['page_number'] ?? '';

        if ($lower_clamp === null) { $this->core->getOutput()->renderJsonFail('Missing lower_clamp parameter'); return; }
        if ($default === null) { $this->core->getOutput()->renderJsonFail('Missing default parameter'); return; }
        if ($max_value === null) { $this->core->getOutput()->renderJsonFail('Missing max_value parameter'); return; }
        if ($upper_clamp === null) { $this->core->getOutput()->renderJsonFail('Missing upper_clamp parameter'); return; }
        if ($page === '') { $this->core->getOutput()->renderJsonFail('Missing page parameter'); }
        if (!is_numeric($lower_clamp)) { $this->core->getOutput()->renderJsonFail('Invalid lower_clamp parameter'); return; }
        if (!is_numeric($default)) { $this->core->getOutput()->renderJsonFail('Invalid default parameter'); return; }
        if (!is_numeric($max_value)) { $this->core->getOutput()->renderJsonFail('Invalid max_value parameter'); return; }
        if (!is_numeric($upper_clamp)) { $this->core->getOutput()->renderJsonFail('Invalid upper_clamp parameter'); return; }
        if (strval(intval($page)) !== $page) { $this->core->getOutput()->renderJsonFail('Invalid page parameter'); }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.save_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save components');
            return;
        }

        $is_notebook_gradeable = ($gradeable->getAutogradingConfig() !== null) && $gradeable->getAutogradingConfig()->isNotebookGradeable();
        if ($is_notebook_gradeable) {
            if ($is_itempool_linked === 'true') {
                if (!$itempool_option) {
                    $this->core->getOutput()->renderJsonFail('Missing itempool_option parameter');
                    return;
                }
            }
        }

        try {
            $component->setTitle($title);
            $component->setTaComment($ta_comment);
            $component->setStudentComment($student_comment);
            $component->setPoints(['lower_clamp' => $lower_clamp, 'default' => $default, 'max_value' => $max_value, 'upper_clamp' => $upper_clamp]);
            $component->setPage($page);
            if ($is_notebook_gradeable) {
                if ($is_itempool_linked === 'true') {
                    $component->setIsItempoolLinked(true);
                    $component->setItempool($itempool_option);
                }
                else {
                    $component->setIsItempoolLinked(false);
                    $component->setItempool('');
                }
            }
            $this->core->getQueries()->saveComponent($component);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    /**
     * Route for saving the order of components in a gradeable
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/order", methods: ["POST"])]
    public function ajaxSaveComponentOrder($gradeable_id) {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (count($order) === 0) { $this->core->getOutput()->renderJsonFail('Missing order parameter'); return; }
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        if (!$this->core->getAccess()->canI("grading.electronic.save_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }
        try {
            $this->saveComponentOrder($gradeable, $order);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function saveComponentOrder(Gradeable $gradeable, array $orders) {
        foreach ($gradeable->getComponents() as $component) {
            if (!isset($orders[$component->getId()])) {
                throw new \InvalidArgumentException('Missing component id in order array');
            }
            $order = $orders[$component->getId()];
            if (!is_int($order) || $order < 0) {
                throw new \InvalidArgumentException('All order values must be non-negative integers');
            }
            $component->setOrder(intval($order));
        }
        $this->core->getQueries()->updateGradeable($gradeable);
    }

    /**
     * Route for saving the page numbers of the components
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/save_pages", methods: ["POST"])]
    public function ajaxSaveComponentPages($gradeable_id) {
        $pages = json_decode($_POST['pages'] ?? '[]', true);
        if (count($pages) === 0) { $this->core->getOutput()->renderJsonFail('Missing pages parameter'); return; }
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        if (!$this->core->getAccess()->canI("grading.electronic.save_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }
        try {
            if (isset($pages['page']) && count($pages) === 1) {
                $this->saveComponentsPage($gradeable, $pages['page']);
            }
            else {
                $this->saveComponentPages($gradeable, $pages);
            }
            $this->core->getQueries()->updateGradeable($gradeable);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function saveComponentPages(Gradeable $gradeable, array $pages) {
        foreach ($gradeable->getComponents() as $component) {
            if (!isset($pages[$component->getId()])) {
                throw new \InvalidArgumentException('Missing component id in pages array');
            }
            $page = $pages[$component->getId()];
            if (!is_int($page)) {
                throw new \InvalidArgumentException('All page values must be integers');
            }
            $component->setPage(max(intval($page), -1));
        }
    }

    public function saveComponentsPage(Gradeable $gradeable, int $page) {
        foreach ($gradeable->getComponents() as $component) {
            $component->setPage(max($page, -1));
        }
    }

    /**
     * Route for adding a new component to a gradeable
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/new", methods:["POST"])]
    public function ajaxAddComponent($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $peer = $_POST['peer'] === 'true';
        if (!$this->core->getAccess()->canI("grading.electronic.add_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to add components');
            return;
        }
        try {
            $page = $gradeable->isPdfUpload() ? ($gradeable->isStudentPdfUpload() ? Component::PDF_PAGE_STUDENT : 1) : Component::PDF_PAGE_NONE;
            $component = $gradeable->addComponent(
                'Problem ' . strval(count($gradeable->getComponents()) + 1),
                '', '', 0, 0, 0, 0, false, $peer, $page
            );
            $component->addMark('No Credit', 0.0, false);
            $this->core->getQueries()->updateGradeable($gradeable);
            $this->core->getOutput()->renderJsonSuccess(['component_id' => $component->getId()]);
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    /**
     * Route for deleting a component from a gradeable
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/delete", methods: ["POST"])]
    public function ajaxDeleteComponent($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        if (!$this->core->getAccess()->canI("grading.electronic.delete_component", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete components');
            return;
        }
        try {
            $gradeable->deleteComponent($component);
            $this->core->getQueries()->updateGradeable($gradeable);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    /**
     * Route for saving a mark's title/point value
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/marks/save", methods: ["POST"])]
    public function ajaxSaveMark($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $mark_id = $_POST['mark_id'] ?? '';
        $points = $_POST['points'] ?? '';
        $title = $_POST['title'] ?? null;
        $publish = ($_POST['publish'] ?? 'false') === 'true';

        if ($title === null) { $this->core->getOutput()->renderJsonFail('Missing title parameter'); return; }
        if ($points === null) { $this->core->getOutput()->renderJsonFail('Missing points parameter'); return; }
        if (!is_numeric($points)) { $this->core->getOutput()->renderJsonFail('Invalid points parameter'); return; }
        $points = floatval($points);

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        $mark = $this->tryGetMark($component, $mark_id);
        if ($mark === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.save_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }
        try {
            $this->saveMark($mark, $points, $title, $publish);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function saveMark(Mark $mark, float $points, string $title, bool $publish) {
        if ($mark->getPoints() !== $points) { $mark->setPoints($points); }
        if ($mark->getTitle() !== $title) { $mark->setTitle($title); }
        if ($mark->isPublish() !== $publish) { $mark->setPublish($publish); }
        $this->core->getQueries()->updateGradeable($mark->getComponent()->getGradeable());
    }

    /**
     * Route for saving mark order in a component
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/marks/save_order", methods: ["POST"])]
    public function ajaxSaveMarkOrder($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (count($order) === 0) { $this->core->getOutput()->renderJsonFail('Missing order parameter'); return; }
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.save_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }
        try {
            $this->saveMarkOrder($component, $order);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function saveMarkOrder(Component $component, array $orders) {
        foreach ($component->getMarks() as $mark) {
            if (!isset($orders[$mark->getId()])) {
                throw new \InvalidArgumentException('Missing mark id in order array');
            }
            $order = $orders[$mark->getId()];
            if (!is_int($order) || $order < 0) {
                throw new \InvalidArgumentException('All order values must be non-negative integers');
            }
            $mark->setOrder(intval($order));
        }
        $this->core->getQueries()->saveComponent($component);
    }

    /**
     * Route for adding a mark to a component
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/marks/add", methods: ["POST"])]
    public function ajaxAddNewMark($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $points = $_POST['points'] ?? '';
        $title = $_POST['title'] ?? null;
        $publish = ($_POST['publish'] ?? 'false') === 'true';

        if ($title === null) { $this->core->getOutput()->renderJsonFail('Missing title parameter'); return; }
        if ($points === null) { $this->core->getOutput()->renderJsonFail('Missing points parameter'); return; }
        if (!is_numeric($points)) { $this->core->getOutput()->renderJsonFail('Invalid points parameter'); return; }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.add_new_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to add mark');
            return;
        }
        try {
            $mark = $this->addNewMark($component, $title, $points, $publish);
            $this->core->getOutput()->renderJsonSuccess(['mark_id' => $mark->getId()]);
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function addNewMark(Component $component, string $title, float $points, bool $publish) {
        $mark = $component->addMark($title, $points, $publish);
        $this->core->getQueries()->saveComponent($component);
        return $mark;
    }

    /**
     * Route for deleting a mark from a component
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/marks/delete", methods: ["POST"])]
    public function ajaxDeleteMark($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $mark_id = $_POST['mark_id'] ?? '';
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        $mark = $this->tryGetMark($component, $mark_id);
        if ($mark === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.delete_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete marks');
            return;
        }
        try {
            $this->deleteMark($mark);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    public function deleteMark(Mark $mark) {
        $mark->getComponent()->deleteMark($mark);
        $this->core->getQueries()->saveComponent($mark->getComponent());
    }

    /**
     * Route for getting all submitters that received a mark and stats about that mark
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/marks/stats", methods: ["POST"])]
    public function ajaxGetMarkStats($gradeable_id) {
        $component_id = $_POST['component_id'] ?? '';
        $mark_id = $_POST['mark_id'] ?? '';
        $grader = $this->core->getUser();
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) { return; }
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) { return; }
        $mark = $this->tryGetMark($component, $mark_id);
        if ($mark === false) { return; }
        if (!$this->core->getAccess()->canI("grading.electronic.get_marked_users", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to view marked users');
            return;
        }
        try {
            $results = $this->getMarkStats($mark, $grader);
            $this->core->getOutput()->renderJsonSuccess($results);
        }
        catch (\InvalidArgumentException $e) { $this->core->getOutput()->renderJsonFail($e->getMessage()); }
        catch (\Exception $e) { $this->core->getOutput()->renderJsonError($e->getMessage()); }
    }

    private function amIBlindGrading($gradeable, $user, $peer) {
        if ($peer && $gradeable->getPeerBlind() === Gradeable::DOUBLE_BLIND_GRADING) {
            return "double";
        }
        if (($peer && $gradeable->getPeerBlind() === Gradeable::SINGLE_BLIND_GRADING) || ($gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING && $this->core->getUser()->getGroup() === User::GROUP_LIMITED_ACCESS_GRADER)) {
            return "single";
        }
        return "unblind";
    }

    private function getMarkStats(Mark $mark, User $grader) {
        $gradeable = $mark->getComponent()->getGradeable();
        $anon = $this->amIBlindGrading($gradeable, $grader, false);
        $section_submitter_ids = $this->core->getQueries()->getSubmittersWhoGotMarkBySection($mark, $grader, $gradeable, $anon);
        $all_submitter_ids     = $this->core->getQueries()->getAllSubmittersWhoGotMark($mark, $anon);
        $submitter_ids = ($grader->accessFullGrading() ? $all_submitter_ids : $section_submitter_ids);

        if ($gradeable->isTeamAssignment()) {
            $submitter_anon_ids = ($anon != 'unblind') ? $submitter_ids : $this->core->getQueries()->getTeamAnonId($submitter_ids);
        }
        else {
            $submitter_anon_ids = ($anon != 'unblind') ? $submitter_ids : $this->core->getQueries()->getAnonId($submitter_ids, $gradeable->getId());
        }

        $section_graded_component_count = 0;
        $section_total_component_count  = 0;
        $total_graded_component_count   = 0;
        $total_total_component_count    = 0;

        $this->getStats($gradeable, $grader, true, $total_graded_component_count, $total_total_component_count);
        $this->getStats($gradeable, $grader, false, $section_graded_component_count, $section_total_component_count);

        return [
            'section_submitter_count' => count($section_submitter_ids),
            'total_submitter_count'   => count($all_submitter_ids),
            'section_graded_component_count' => $section_graded_component_count,
            'total_graded_component_count'   => $total_graded_component_count,
            'section_total_component_count' => $section_total_component_count,
            'total_total_component_count'   => $total_total_component_count,
            'submitter_ids' => $submitter_ids,
            'submitter_anon_ids' => $submitter_anon_ids
        ];
    }

    private function getStats(Gradeable $gradeable, User $grader, bool $full_stats, &$total_graded, &$total_total) {
        $num_components = $this->core->getQueries()->getTotalComponentCount($gradeable->getId());
        $sections = [];
        if ($full_stats) {
            $sections = $this->core->getQueries()->getAllSectionsForGradeable($gradeable);
        }
        elseif ($gradeable->isGradeByRegistration()) {
            $sections = $grader->getGradingRegistrationSections();
        }
        else {
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(), $grader->getId());
        }

        $include_withdrawn_students = ($_COOKIE['include_withdrawn_students'] ?? 'omit') === 'include';
        $section_key = ($gradeable->isGradeByRegistration() ? 'registration_section' : 'rotating_section');
        $total_users       = [];
        $graded_components = [];
        if (count($sections) > 0) {
            $total_users = ($gradeable->isTeamAssignment()) ?
            $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable->getId(), $sections, $section_key) :
            $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key, $include_withdrawn_students);
            $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable->getId(), $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);
        }

        foreach ($graded_components as $key => $value) {
            $total_graded += intval($value);
        }
        foreach ($total_users as $key => $value) {
            $total_total += $value * $num_components;
        }
    }
}
