<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\GradeableList;

class TeamController extends AbstractController {

    /** @var GradeableList */
    private $gradeables_list;

    public function __construct(Core $core) {
        parent::__construct($core);
        $this->gradeables_list = $this->core->loadModel(GradeableList::class);
    }

    public function run() {
        switch ($_REQUEST['action']) {
            case 'create_new_team':
                $this->createNewTeam();
                break;
            case 'leave_team':
                $this->leaveTeam();
                break;
            case 'invitation':
                $this->sendInvitation();
                break;
            case 'accept':
                $this->acceptInvitation();
                break;
            case 'show_page':
            default:
                $this->showPage();
                break;
        }
    }

    private function getTeamByUserId($g_id, $user_id) {
        $teams = $this->core->getQueries()->getTeamsByGradeableId($g_id);
        foreach ($teams as $team) {
            if ($team->hasMember($user_id)) {
                return $team;
            }
        }
        return null;
    }

    public function createNewTeam() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        if ($this->getTeamByUserId($gradeable_id, $user_id) === null) {
            $this->core->getQueries()->removeTeamUser($gradeable_id, $user_id);
            $this->core->getQueries()->newTeam($gradeable_id, $user_id);
        }
        $this->showPage();
    }

    public function leaveTeam() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        if ($this->getTeamByUserId($gradeable_id, $user_id) !== null) {
            $this->core->getQueries()->removeTeamUser($gradeable_id, $user_id);
        }
        $this->showPage();
    }

    public function sendInvitation() {
        if (isset($_POST['invite_id']) && ($_POST['invite_id'] !== '')) {
            if ($this->core->getQueries()->getUserByID($_POST['invite_id'])->isLoaded()) {
                $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
                $invite_id = $_POST['invite_id'];
                if ($this->getTeamByUserId($gradeable_id, $invite_id) === null) {
                    $user_id = $this->core->getUser()->getId();
                    $team = $this->getTeamByUserId($gradeable_id, $user_id);
                    if (!$team->sentInvite($invite_id)) {
                        $team_id = $team->getId();
                        $this->core->getQueries()->newTeamInvite($team_id, $invite_id);
                    }
                    //else error user already invited
                }
                //else error user has a team
            }
            //else error user does not exist
        }
        //else error no user specified
        $this->showPage();
    }

    public function acceptInvitation() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $team_id = (isset($_REQUEST['team_id'])) ? $_REQUEST['team_id'] : null;
        $user_id = $this->core->getUser()->getId();
        if ($this->getTeamByUserId($gradeable_id, $user_id) === null) {
            $this->core->getQueries()->removeTeamUser($gradeable_id, $user_id);
            $this->core->getQueries()->newTeamMember($team_id, $user_id);
        }
        $this->showPage();
    }

    public function showPage() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
        $this->core->getOutput()->renderOutput(array('submission', 'Team'), 'showTeamPage', $gradeable, $teams);
    }
}
