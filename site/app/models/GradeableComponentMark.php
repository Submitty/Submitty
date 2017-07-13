<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class GradeableComponentMark
 *
 */

class GradeableComponent extends AbstractModel {
	/** @property @var int Unique identifier for the mark */
    protected $id = null;
    /** @property @var float Given points that someone has given this mark */
    protected $point = 0;
    /** @property @var int Order for marks to be shown in */
    protected $order = 1;
    /** @property @var int if the mark is a deduction or addition*/
    protected $type = 0;
    /** @property @var string Comment for the mark */
    protected $note = "";
    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (!isset($details['gcm_id'])) {
            return;
        }
        $this->id = $details['gcm_id'];
        $this->point = $details['gcm_point'];
        $this->order = $details['gcm_order'];
        $this->type = $details['gcm_type'];
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