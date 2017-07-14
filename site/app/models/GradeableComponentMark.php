<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class GradeableComponentMark
 *
 */

class GradeableComponentMark extends AbstractModel {
	/** @property @var int Unique identifier for the mark */
    protected $id = null;
    /** @property @var int Unique identifier for the component associated with this mark */
    protected $gc_id = null;
    /** @property @var float Given points that someone has given this mark */
    protected $points = 0;
    /** @property @var int Order for marks to be shown in */
    protected $order = 1;
    /** @property @var string Comment for the mark */
    protected $note = "";
    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (!isset($details['gcm_id'])) {
            return;
        }
        $this->id = $details['gcm_id'];
        $this->gc_id = $details['gc_id'];
        $this->points = $details['gcm_points'];
        $this->order = $details['gcm_order'];
        $this->note = $details['gcm_note'];
    }

    public function saveData($gd_id, $gc_id) {
        if ($this->modified) {
        	if ($this->id === null) {
                
        	} else {
        		$this->core->getQueries()->updateGradeableComponentMarkData($gcd_id, $this);
        	}
        }
    }
}