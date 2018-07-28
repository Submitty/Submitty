<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\GradeableList;

class NavigationController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'no_access':
                $this->noAccess();
                break;
            case 'notifications':
                $this->notificationsHandler();
                break;
            default:
                $this->navigationPage();
                break;
        }
    }

    private function noAccess() {
        $this->core->getOutput()->renderOutput('Navigation', 'noAccessCourse');
    }

    private function navigationPage() {
        $gradeables_list = new GradeableList($this->core);
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=PT+Sans:700,700italic");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Inconsolata");
        
        $future_gradeables_list = $gradeables_list->getFutureGradeables();
        $beta_gradeables_list = $gradeables_list->getBetaGradeables();
        $open_gradeables_list = $gradeables_list->getOpenGradeables();
        $closed_gradeables_list = $gradeables_list->getClosedGradeables();
        $grading_gradeables_list = $gradeables_list->getGradingGradeables();
        $graded_gradeables_list = $gradeables_list->getGradedGradeables();
        
        $sections_to_lists = [];

        $user = $this->core->getUser();

        if ($user->accessGrading()) {
            $sections_to_lists[GradeableList::FUTURE] = $future_gradeables_list;
            $sections_to_lists[GradeableList::BETA] = $beta_gradeables_list;
        }

        $sections_to_lists[GradeableList::OPEN] = $open_gradeables_list;
        $sections_to_lists[GradeableList::CLOSED] = $closed_gradeables_list;
        $sections_to_lists[GradeableList::GRADING] = $grading_gradeables_list;
        $sections_to_lists[GradeableList::GRADED] = $graded_gradeables_list;

        //Remove gradeables we are not allowed to view
        foreach ($sections_to_lists as $key => $value) {
            $sections_to_lists[$key] = array_filter($value, array($this, "filterCanView"));
        }

        //Clear empty sections
        foreach ($sections_to_lists as $key => $value) {
            // if there are no gradeables, don't show this category
            if (count($sections_to_lists[$key]) == 0) {
                unset($sections_to_lists[$key]);
            }
        }

        // Get a single array of the visible gradeables
        $visible_gradeables = [];
        foreach($sections_to_lists as $gradeables) {
            foreach($gradeables as $gradeable) {
                $visible_gradeables[] = $gradeable;
            }
        }

        // Get the user data for each gradeable
        $graded_gradeables = [];
        if (count($visible_gradeables) !== 0) {
            foreach ($this->core->getQueries()->getGradedGradeables($visible_gradeables, $user->getId()) as $gg) {
                $graded_gradeables[$gg->getGradeableId()] = $gg;
            }
        }

        $this->core->getOutput()->renderOutput('Navigation', 'showGradeables', $sections_to_lists, $graded_gradeables);
        $this->core->getOutput()->renderOutput('Navigation', 'deleteGradeableForm'); 
    }
    
    /**
     * Test if the current user is allowed to view this gradeable
     * @param Gradeable $gradeable
     * @return bool True if they are
     */
    private function filterCanView(Gradeable $gradeable) {
        $user = $this->core->getUser();

        //Remove incomplete gradeables for non-instructors
        if (!$user->accessAdmin() && $gradeable->getType() == GradeableType::ELECTRONIC_FILE &&
            !$gradeable->hasAutogradingConfig()) {
            return false;
        }

        // student users should only see electronic gradeables -- NOTE: for now, we might change this design later
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE && !$user->accessGrading()) {
            return false;
        }

        // if student view false, never show
        if (!$gradeable->isStudentView() && !$user->accessGrading()) {
            return false;
        }

        //If we're not instructor and this is not open to TAs
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($gradeable->getTaViewStartDate() > $date && !$user->accessAdmin()) {
            return false;
        }

        return true;
    }

    private function getElapsedTime($elapsed_time, $actual_time){
        if($elapsed_time < 60){
            return "Less than a minute ago";
        } else if($elapsed_time < 3600){
            $minutes = floor($elapsed_time/60);
            if($minutes == 1)
                return "1 minute ago";
            else
                return "{$minutes} minutes ago";
        } else if($elapsed_time < 3600*24){
            $hours = floor($elapsed_time/3600);
            if($hours == 1)
                return "1 hour ago";
            else
                return "{$hours} hours ago";
        } else {
            return date_format(date_create($actual_time), "n/j g:i A");
        }
    }

    private function notificationsHandler() {
        $user_id = $this->core->getUser()->getId();
        if(!empty($_GET['action']) && $_GET['action'] == 'mark_as_read') {
            $this->core->getQueries()->markNotificationAsRead($user_id, $_GET['nid']);
            $this->core->redirect($this->core->buildUrl(array('component' => 'navigation', 'page' => 'notifications')));
        } else {
            // Show Notifications
            $show_all = (!empty($_GET['show_all']) && $_GET['show_all'])?true:false;
            $notifications = $this->core->getQueries()->getUserNotifications($user_id, $show_all);
            foreach ($notifications as &$notification) {
                $notification['url'] = $this->core->buildUrl(json_decode($notification['metadata']));
                $notification['time'] = $this->getElapsedTime($notification['elapsed_time'], $notification['created_at']);
            }
            $this->core->getOutput()->renderOutput('Navigation', 'listNotifications', $notifications, $show_all);
        }
    }
}
