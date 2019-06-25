<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\Core;
use app\models\gradeable\LateDays;
use app\models\User;

class LateDaysTableController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'plugin_table':
                $this->showLateTable();
                break;
            default:
                $this->showLateTablePage($this->core->getUser());
                break;
        }
    }

    private function showLateTable() {
        $user_id = $_REQUEST['user_id'] ?? '';
        $highlight_id = $_REQUEST['g_id'] ?? '';
        $user = $this->core->getQueries()->getUserById($user_id);
        $this->core->getOutput()->renderString(self::renderLateTable($this->core, $user, $highlight_id));
    }

    /**
     * Renders the output for the late days table for a user
     * @param Core $core
     * @param User $user
     * @param string $highlight_gradeable_id
     * @return string
     */
    public static function renderLateTable(Core $core, User $user, string $highlight_gradeable_id) {
        return $core->getOutput()->renderTemplate(array('LateDaysTable'), 'showLateTable', LateDays::fromUser($core, $user), $highlight_gradeable_id);
    }

    /**
     * Renders the 'my late days' page
     * @param User $user
     */
    private function showLateTablePage(User $user) {
        $this->core->getOutput()->renderOutput(array('LateDaysTable'), 'showLateTablePage', LateDays::fromUser($this->core, $user));
    }

}