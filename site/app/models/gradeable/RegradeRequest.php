<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\AbstractModel;

/**
 * Class RegradeRequest
 * @package app\models\gradeable
 *
 * @method \DateTime getTimestamp()
 * @method int getStatus()
 * @method void setStringStatus($string_status)
 * @method string getStringStatus()
 * @method int getGcId()
 */
class RegradeRequest extends AbstractModel {

    const STATUS_RESOLVED = 0;
    const STATUS_ACTIVE = -1;

    const STRING_STATUS_NO_SUBMISSION = 'no_submission';
    const STRING_STATUS_NONE = 'none';
    const STRING_STATUS_PENDING_GRADING = 'pending_grading';
    const STRING_STATUS_PENDING = 'pending';
    const STRING_STATUS_RESOLVED_GRADING = 'resolved_grading';
    const STRING_STATUS_RESOLVED = 'resolved';

    /** @var int The unique Id of this grade inquiry */
    private $id = 0;
    /** @property @var \DateTime The timestamp (readonly) of most recent update to $status */
    protected $timestamp = null;
    /** @property @var int The status of the grade inquiry */
    protected $status = self::STATUS_RESOLVED;
    /** @property @var string The string description of the state the grade inquiry is in */
    protected $string_status = self::STRING_STATUS_NO_SUBMISSION;
    /** @property @var int|null The gradeable component that this grade inquiry is referencing */
    protected $gc_id = null;



    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $this->setId($details['id']);
        $this->setStatus($details['status']);
        $this->timestamp = DateUtils::parseDateTime($details['timestamp'], $this->core->getConfig()->getTimezone());
        $this->modified = false;
        $this->gc_id = $details['gc_id'];
    }

    /**
     * Internal method to set and sanity check the grade inquiry id
     * @param int $id
     */
    private function setId(int $id) {
        if ($id < 1) {
            throw new \InvalidArgumentException('Grade inquiry ids must be > 0');
        }
        $this->id = $id;
    }

    /**
     * Get the id of this grade inquiry
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Sets the status of the grade inquiry
     * @param int $status
     */
    public function setStatus(int $status) {
        if (!in_array($status, [self::STATUS_RESOLVED, self::STATUS_ACTIVE])) {
            throw new \InvalidArgumentException('Invalid grade inquiry status');
        }
        $this->status = $status;
        $this->modified = true;
    }
}
