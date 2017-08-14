<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class GradeableComponentMark
 *
 * @method int getId()
 * @method int getGcId()
 * @method int getOrder()
 * @method float getPoints()
 */

class GradeableComponentMark extends AbstractModel {
	/** @property @var int Unique identifier for the mark */
    protected $id = null;
    /** @property @var int Unique identifier for the component associated with this mark */
    protected $gc_id = null;
    /** @property @var int Order for marks to be shown in */
    protected $order = 1;
    /** @property @var float Points for this mark */
    protected $points = 0;
    /** @property @var string Comment for this mark */
    protected $note = "";
    /** @property @var bool This person earned this mark*/
    protected $has_mark = false;
    /** @property @var bool What the database says about if this person earned this mark*/
    protected $original_has_mark = false;

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
        if (isset($details['gcm_has_mark']) && $details['gcm_has_mark'] !== null) {
            $this->has_mark = true;
            $this->original_has_mark = true;
        }
    }

    public function save() {
        if($this->id === null) {
            return $this->core->getQueries()->createGradeableComponentMark($this);
        } else {
            $this->core->getQueries()->updateGradeableComponentMark($this);
        }
    }

    public function saveData($gd_id, $gc_id) {
        if($this->modified){
            if($this->has_mark != $this->original_has_mark) {
            	if ($this->has_mark) {
                    $this->core->getQueries()->insertGradeableComponentMarkData($gd_id, $gc_id, $this);
            	} else {
            		$this->core->getQueries()->deleteGradeableComponentMarkData($gd_id, $gc_id, $this);
            	}
            }
        }
    }

    public function setNote($temp_note) {
        $this->note = urlencode($temp_note);
    }

    //use this when inserting into the database
    public function getNoteNoDecode(){
        return ($this->note);
    }

    public function getNote() {
        return(urldecode($this->note));
    }
}
