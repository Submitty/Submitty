<?php
/**
 * Created by PhpStorm.
 * User: mackek4
 * Date: 6/25/2018
 * Time: 2:30 PM
 */

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class AutoGradedGradeable
 * @package app\models\gradeable
 *
 * @method int getActiveVersion()
 * @method AutoGradedVersion[] getAutoGradedVersions()
 */
class AutoGradedGradeable extends AbstractModel {
    /** @property @var GradedGradeable A reference to the graded gradeable this auto grade belongs to */
    private $graded_gradeable = null;
    /** @property @var int The active submission version for electronic gradeables */
    protected $active_version = 0;
    /** @property @var AutoGradedVersion[] The graded versions for electronic gradeables */
    protected $auto_graded_versions = array();
    protected $queue_status;

    /**
     * AutoGradedGradeable constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable
     * @param array $details
     * @throws \InvalidArgumentException if any of the details are invalid or the graded gradeable is null
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        $this->setActiveVersion($details['active_version']);
        if($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;
        $this->modified = false;
    }

    public function toArray() {
        $details = parent::toArray();

        // Uncomment this block if we want to serialize the scores
//        $visible_percent = $this->getVisiblePercent();
//        $visible_percent = is_nan($visible_percent) ? 0 : $visible_percent;
//        $details['visible_score'] = $visible_percent;
//
//        $total_score = $this->getTotalPercent();
//        $total_score = is_nan($total_score) ? 0 : $total_score;
//        $details['total_score'] = $total_score;

        return $details;
    }

    private function loadQueueStatus() {
        $interactive_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_queue";

        // FIXME: batch queue has gone away!
        $batch_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_batch";

        $submitter_id = $this->graded_gradeable->getSubmitter()->getId();

        $queue_file = implode("__", array($this->core->getConfig()->getSemester(),
            $this->core->getConfig()->getCourse(), $this->graded_gradeable->getGradeable()->getId(),
            $submitter_id, $this->active_version));
        $grading_queue_file = "GRADING_".$queue_file;

        //TODO: STOPPED HERE

        $this->in_interactive_queue = file_exists($interactive_queue."/".$queue_file);
        $this->in_batch_queue = file_exists($batch_queue."/".$queue_file);
        $this->grading_interactive_queue = file_exists($interactive_queue."/".$grading_queue_file);
        $this->grading_batch_queue = file_exists($batch_queue."/".$grading_queue_file);

        $queue_count = 0;
        $grading_count = 0;
        if($this->in_interactive_queue === true) {
            $files = scandir($interactive_queue);
            $f = array();
            $times = array();
            foreach($files as $file) {
                if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".") && !in_array($file, $f)) {
                    $f[] = $file;
                    $times[] = filemtime($interactive_queue.'/'.$file);
                }
            }
            array_multisort($times,SORT_DESC,$f); //Sorted By Descending Here

            foreach($f as $file) {
                if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".")) {
                    if(strpos($file, "GRADING_") !== false) {
                        $grading_count = $grading_count + 1;
                    }
                    else {
                        $queue_count = $queue_count + 1;
                        if($file === $queue_file) {
                            $this->interactive_queue_position = $queue_count;
                        }
                    }
                }
            }

            /* Note:  Once permissions to access batch queue from interactive queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /*$files = @scandir($batch_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/

            $this->interactive_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        else if($this->in_batch_queue === true) {
            $files = scandir($batch_queue);
            $f = array();
            $times = array();
            foreach($files as $file){
                if(is_file($batch_queue.'/'.$file)){
                    $f[] = $file;
                    $times[] = filemtime($batch_queue.'/'.$file);
                }
            }
            array_multisort($times,SORT_DESC,$f); //Sort By Descending Here

            foreach($f as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
                else {
                    $queue_count = $queue_count + 1;
                    if($file === $queue_file) {
                        $this->batch_queue_position = $queue_count;
                    }
                }
            }

            /* Note:  Once permissions to access interactive queue from batch queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /* $files = @scandir($interactive_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/
            $this->batch_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        if($this->in_interactive_queue === false && $this->in_batch_queue === false) {
            $this->interactive_queue_position = 0;
            $this->interactive_queue_total = 0;
            $this->batch_queue_position = 0;
            $this->batch_queue_total = 0;
            $this->grading_total = 0;
        }
    }


    /**
     * Gets the AutoGradedVersion instance for the active version
     * @return AutoGradedVersion
     */
    public function getActiveVersionInstance() {
        return $this->autograding_versions[$this->active_version];
    }

    /**
     * Sets the active submission version for this electronic gradeable
     * @param int $version
     */
    public function setActiveVersion($version) {
        if ((is_int($version) || ctype_digit($version)) && intval($version) >= 0) {
            $this->active_version = intval($version);
        } else {
            throw new \InvalidArgumentException('Active version must be a non-negative integer');
        }
        $this->modified = true;
    }

    /**
     * Sets the array of autograding versions for this gradeable data
     * @param AutoGradedVersion[] $auto_graded_versions
     */
    public function setAutogradingVersions(array $auto_graded_versions) {
        foreach ($auto_graded_versions as $auto_graded_version) {
            if (!($auto_graded_version instanceof AutoGradedVersion)) {
                throw new \InvalidArgumentException('Autograding version array contained invalid type');
            }
        }
        $this->auto_graded_versions = [];
        foreach ($auto_graded_versions as $auto_graded_version) {
            $this->auto_graded_versions[$auto_graded_version->getVersion()] = $auto_graded_version;
        }
    }

    /* Percentage score access methods */

    /**
     * Gets the percent of the possible visible points the submitter earned
     *  for the active version
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no visible percent or no active version
     */
    public function getNonHiddenPercent($clamp = false) {
        $instance = $this->getActiveVersionInstance();
        if($instance === null) {
            return NAN;
        }
        return $instance->getNonHiddenPercent($clamp);
    }

    /**
     * Gets the percent of all possible points the submitter earned
     *  for the active version
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no points possible or no active version
     */
    public function getTotalPercent($clamp = false) {
        $instance = $this->getActiveVersionInstance();
        if($instance === null) {
            return NAN;
        }
        return $instance->getTotalPercent($clamp);
    }
}