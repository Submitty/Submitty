<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\database\DatabaseQueriesPostgresql;
use app\libraries\Core;
use app\libraries\GradeableType;
use app\views\submission\HomeworkView;

use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\GradeableList;

class UploadController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
        $this->gradeables_list = $this->core->loadModel("GradeableList", $this->core);
    }

    public function run() {
        switch($_REQUEST['action']) {
            case 'upload':
                //return $this->ajaxUploadSubmission();
                break;
            case 'update':
                //return $this->updateSubmissionVersion();
                break;
            case 'check_refresh':
                //return $this->checkRefresh();
                break;
            case 'display':
            default:
                return $this->showUploadPage();
                break;
        }
    }

    public function showUploadPage() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        if ($gradeable !== null) {
            $error = false;
            $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

            if ($gradeable->getOpenDate() > $now) {
                $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'noGradeable', $gradeable_id);
                return array('error' => true, 'message' => 'No gradeable with that id.');
            }
            else {
                $loc = array('component' => 'student',
                             'gradeable_id' => $gradeable->getId());
                $this->core->getOutput()->addBreadcrumb($gradeable->getName(), $this->core->buildUrl($loc));
                if (!$gradeable->hasConfig()) {
                    $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                           'showGradeableError', $gradeable);
                    $error = true;
                }
                else {
                    $gradeable->loadResultDetails();
                    $days_late = DateUtils::calculateDayDiff($gradeable->getDueDate());
                    if ($gradeable->beenTAgraded() && $gradeable->hasGradeFile()) {
                        $gradeable->updateUserViewedDate();
                    }
                    $this->core->getOutput()->renderOutput(array('grading', 'Upload'), 'showUpload', $gradeable, $days_late);
                }
            }
            return array('id' => $gradeable_id, 'error' => $error);
        }
        else {
            $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'noGradeable', $gradeable_id);
            return array('error' => true, 'message' => 'No gradeable with that id.');
        }
    }


}
