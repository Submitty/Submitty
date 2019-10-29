<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\Core;
use app\models\gradeable\LateDays;
use app\models\User;
use Symfony\Component\Routing\Annotation\Route;

class LateDaysTableController extends AbstractController {
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
     * @Route("/{_semester}/{_course}/late_table")
     */
    public function showLateTablePage() {
        $this->core->getOutput()->renderOutput(array('LateDaysTable'), 'showLateTablePage', LateDays::fromUser($this->core, $this->core->getUser()));
    }

}
