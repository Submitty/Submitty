<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class SimpleStat

 * @method string getTitle()
 * @method float getMaxValue()
 * @method int getOrder()
 * @method float getAverageScore()
 * @method float getStandardDeviation()
 * @method bool getIsPeer()

 */
class SimpleStat extends AbstractModel {
    /** @property @var string Title of the component shown to students and graders */
    protected $title = "";
    /** @property @var float Maximum value that the component can have */
    protected $max_value = 0;
    /** @property @var int Order for components to be shown in */
    protected $order = 1;
    /** @property @var float Average grade of people with a grade for this component */
    protected $average_score = 0;
    /** @property @var float Standard deviation of people with a grade for this component */
    protected $standard_deviation = 0;
    /** @property @var bool Does this component use peer grading*/
    protected $is_peer = false;

    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        $this->title = $details['gc_title'];
        $this->max_value = $details['gc_max_value'];
        $this->order = $details['gc_order'];
        $this->average_score = $details['avg_comp_score'];
        $this->standard_deviation = $details['std_dev'];
        $this->is_peer = $details['gc_is_peer'];
    }
}
