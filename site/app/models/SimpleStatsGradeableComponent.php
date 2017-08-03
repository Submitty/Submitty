<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class SimpleStatsGradeableComponent

 * @method int getId()
 * @method string getTitle()
 * @method float getMaxValue()
 * @method int getOrder()
 * @method float getAverageScore()
 * @method int getSection()
 * @method string getSectionType()
 * @method bool getIsPeer()

 */
class SimpleStatsGradeableComponent extends AbstractModel {
    /** @property @var int Unique identifier for the component */
    protected $id = null;
    /** @property @var string Title of the component shown to students and graders */
    protected $title = "";
    /** @property @var float Maximum value that the component can have */
    protected $max_value = 0;

//    /** @property @var bool Is the component extra credit for this gradeable */
//    protected $is_extra_credit = false;

    /** @property @var int Order for components to be shown in */
    protected $order = 1;
    /** @property @var float Average grade of people with a grade for this component */
    protected $average_score = 0;
    /** @property @var int section that the average_score applies to */
    protected $section = null;
    /** @property @var string rotating or registration section */
    protected $section_type = null;
    /** @property @var bool Does this component use peer grading*/
    protected $is_peer = false;

    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (!isset($details['gc_id'])) {
            return;
        }
        $this->id = $details['gc_id'];
        $this->title = $details['gc_title'];
        $this->max_value = $details['gc_max_value'];
//        $this->is_extra_credit = $details['gc_is_extra_credit'];
        $this->order = $details['gc_order'];
        $this->average_score = $details['avg_comp_score'];
        $this->section = $details['section'];
        $this->section = $details['section_type'];
        $this->is_peer = isset($details['gc_is_peer']) ? $details['gc_is_peer']: false;
    }
    
    /**
     * @raises \BadMethodCallException
     */
    public function setId() {
        throw new \BadMethodCallException('Call to undefined method '.__CLASS__.'::setId()');
    }
}
