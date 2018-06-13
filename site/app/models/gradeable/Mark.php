<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class Mark
 * @package app\models\gradeable
 *
 * @method getId();
 * @method setId($id);
 * @method getPoints();
 * @method getNote();
 * @method setNote($note);
 * @method getOrder();
 * @method setOrder($order);
 * @method isPublish();
 * @method setPublish($should_publish);
 */
class Mark extends AbstractModel
{
    /** @property @var int The course-wide unique numeric id of this mark */
    protected $id = -1;
    /** @property @var int The number of points this mark will add to the score (negative for deductions) */
    protected $points = 0;
    /** @property @var string The description of this mark (aka why a student would lose/gain these points) */
    protected $note = "";
    /** @property @var int The order of the mark within the component */
    protected $order = 0;
    /** @property @var bool If the student should be able to see this mark */
    protected $publish = false;

    public function __construct(Core $core, $details)
    {
        parent::__construct($core);

        $this->id = $details['id'];
        $this->points = $details['points'];
        $this->note = $details['note'];
        $this->order = $details['order'];
        $this->publish = $details['publish'];
    }

    /* Overridden setters with validation */

    public function setPoints($points)
    {
        if(is_float($points) || is_int($points)) {
            $this->points = $points;
        } else {
            throw new \InvalidArgumentException("Mark points must be a number!");
        }
    }
}