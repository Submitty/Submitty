<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class Mark
 * @package app\models\gradeable
 *
 * @method int getId()
 * @method float getPoints()
 * @method string getTitle()
 * @method void setTitle($title)
 * @method int getOrder()
 * @method void setOrder($order)
 * @method bool isPublish()
 * @method void setPublish($should_publish)
 */
class Mark extends AbstractModel
{
    /** @var Component Reference to the component this belongs to */
    private $component = null;
    /** @property @var int The course-wide unique numeric id of this mark */
    protected $id = -1;
    /** @property @var float The number of points this mark will add to the score (negative for deductions) */
    protected $points = 0;
    /** @property @var string The description of this mark (aka why a student would lose/gain these points) */
    protected $title = "";
    /** @property @var int The order of the mark within the component */
    protected $order = 0;
    /** @property @var bool If the student should be able to see this mark */
    protected $publish = false;

    public function __construct(Core $core, Component $component, $details)
    {
        parent::__construct($core);

        $this->setComponent($component);
        $this->setIdInternal($details['id']);
        $this->setPoints($details['points']);
        $this->setTitle($details['title']);
        $this->setOrder($details['order']);
        $this->setPublish($details['publish']);
    }

    /**
     * Gets the mark's component
     * @return Component The component the mark belongs to
     */
    public function getComponent()
    {
        return $this->component;
    }

    /* Overridden setters with validation */
    /**
     * Sets the mark's component
     * @param Component $component A non-null component
     */
    private function setComponent(Component $component)
    {
        if($component === null) {
            throw new \InvalidArgumentException('Component Cannot be null!');
        }
        $this->component = $component;
    }

    /**
     * Sets the mark id
     * @param int $id Must be a non-negative integer
     */
    private function setIdInternal($id)
    {
        if (is_int($id) && $id >= 0) {
            $this->id = $id;
        } else {
            throw new \InvalidArgumentException('Mark Id must be an integer >= 0');
        }
    }
    /** @internal */
    public function setId($id)
    {
        throw new \BadFunctionCallException('Cannot set Id of mark');
    }

    /**
     * Sets the points this mark is worth
     * @param string|float|int $points Must be a number.  Will be rounded to precision of gradeable.
     */
    public function setPoints($points)
    {
        if(is_numeric($points)) {
            $this->points = $this->getComponent()->getGradeable()->roundPointValue($points);
        } else {
            throw new \InvalidArgumentException('Mark points must be a number!');
        }
    }
}
