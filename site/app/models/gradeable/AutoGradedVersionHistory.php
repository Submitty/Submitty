<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\AbstractModel;

/**
 * Class AutoGradedVersionHistory
 * @package app\models\gradeable
 *
 * @method \DateTime getQueueTime()
 * @method bool isBatchRegrade()
 * @method \DateTime getGradingBegan()
 * @method int getWaitTime()
 * @method int getAccessDuration()
 * @method \DateTime getGradingFinished()
 * @method int getGradeTime()
 * @method string getVcsRevision()
 * @method \DateTime getSubmissionTimestamp()
 */
class AutoGradedVersionHistory extends AbstractModel {

    /** @prop @var \DateTime Time the version went into the queue */
    protected $queue_time = null;
    /** @prop @var bool If the grading run was a batch regrade */
    protected $batch_regrade = false;
    /** @prop @var \DateTime The time the grading began */
    protected $grading_began = null;
    /** @prop @var int Seconds between first access and submission of this version */
    protected $access_duration = 0;
    /** @prop @var int Seconds the version spent in the queue */
    protected $wait_time = 0;
    /** @prop @var \DateTime The time the grading finished */
    protected $grading_finished = null;
    /** @prop @var int Seconds the version took to grade */
    protected $grade_time = 0;
    /** @prop @var string VCS revision id (i.e. git commit hash) */
    protected $vcs_revision = '';
    /** @prop @var \DateTime The time of the most recent submission */
    protected $submission_time = null;
    /** @prop @var \DateTime The time of the most recent submission */
    protected $first_access_time = null;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $timezone = $this->core->getConfig()->getTimezone();
        $this->queue_time = DateUtils::parseDateTime($details['queue_time'], $timezone);
        $this->grading_began = DateUtils::parseDateTime($details['grading_began'], $timezone);
        $this->grading_finished = DateUtils::parseDateTime($details['grading_finished'], $timezone);
        $this->batch_regrade = $details['batch_regrade'] === true;
        $this->first_access_time = null;
        if (array_key_exists('first_access_time', $details)) {
            $this->first_access_time = DateUtils::parseDateTime($details['first_access_time'], $timezone);
        }
        $this->access_duration = $details['access_duration'] ?? '-1';
        $this->wait_time = $details['wait_time'];
        $this->grade_time = $details['grade_time'];
        $this->submission_time = DateUtils::parseDateTime($details['submission_time'], $timezone);
        $this->vcs_revision = $details['revision'] ?? '';
    }

    /** @internal */
    private function setQueueTime() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setBatchRegrade() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setGradingBegan() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setWaitTime() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setGradingFinished() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setGradeTime() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }

    /** @internal */
    private function setVcsRevision() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedVersionHistory');
    }
}
