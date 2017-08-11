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
 * @method int getCount()
 * @method bool getIsPeer()

 */
class SimpleStat extends AbstractModel {
    /** @ property @var bool is this a component */
    protected $component = true;
    /** @property @var string Title of gradeable or component */
    protected $title = "";
    /** @property @var float Maximum value of gradeable or component*/
    protected $max_value = 0;
    /** @property @var int Order for components to be shown in */
    protected $order = -1;
    /** @property @var float Average grade */
    protected $average_score = 0;
    /** @property @var float Standard deviation*/
    protected $standard_deviation = 0;
    /** @property @var int number of people graded(completely graded)*/
    protected $count = 0;
    /** @property @var bool Does this component use peer grading*/
    protected $is_peer = null;

    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if(isset($details['gc_id'])) {
            $this->component = true;
            $this->title = $details['gc_title'];
            $this->max_value = $details['gc_max_value'];
            $this->average_score = $details['avg_comp_score'];
            $this->standard_deviation = $details['std_dev'];
            $this->order = $details['gc_order'];
            $this->is_peer = $details['gc_is_peer'];
            $this->count = $details['count'];
        }
        else {
            $this->component = false;
            $this->max_value = $details['max'];
            $this->average_score = $details['avg_score'];
            $this->standard_deviation = $details['std_dev'];
            $this->count = $details['count'];
        }
    }
}
