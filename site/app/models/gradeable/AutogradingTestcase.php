<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;

/**
 * Class AutogradingTestcase
 * @package app\models\gradeable
 *
 * @method getIndex();
 * @method getName();
 * @method getDetails()
 * @method getPoints()
 * @method isExtraCredit()
 * @method isHidden()
 * @method isReleaseHiddenDetails()
 * @method getDispatcherActions()
 * @method getGraphicsActions()
 * @method isPublishActions()
 */
class AutogradingTestcase extends AbstractModel {

    /** @prop @var string The index of this testcase in the autograding config */
    protected $index;

    /** @prop @var string Name of this testcase */
    protected $name = "";
    /** @prop @var string TODO: The command to run? */
    protected $details = "";
    /** @prop @var int Number of points this testcase is worth */
    protected $points = 0;
    /** @prop @var bool If this testcase is extra credit */
    protected $extra_credit = false;
    /** @prop @var bool If this testcase is hidden */
    protected $hidden = false;
    /** @prop @var bool If testcase details should be released when grades are released for a hidden testcase */
    protected $release_hidden_details = false;
    /** @prop @var bool If the user should see the message from a GradedAutogradingTestCase */
    protected $view_testcase_message = true;
    /** @prop @var string */
    protected $testcase_label = '';
    /** @prop-read @var array */
    protected $dispatcher_actions = [];
    /** @prop-read @var array */
    protected $graphics_actions = [];
    /** @prop-read @var boolean */
    protected $publish_actions = false;

    /**
     * GradeableTestcase constructor.
     *
     * @param Core $core
     * @param array $testcase
     * @param int $idx
     */
    public function __construct(Core $core, $testcase, $idx) {
        parent::__construct($core);
        $this->index = $idx;

        $this->name = Utils::prepareHtmlString($testcase['title'] ?? '');
        $this->details = $testcase['details'] ?? '';
        $this->points = intval($testcase['points'] ?? 0);
        $this->extra_credit = ($testcase['extra_credit'] ?? false) === true;
        $this->hidden = ($testcase['hidden'] ?? false) === true;
        $this->release_hidden_details = ($testcase['release_hidden_details'] ?? false) === true;
        $this->view_testcase_message = ($testcase['view_testcase_message'] ?? true) === true;
        $this->testcase_label = $testcase['testcase_label'] ?? '';
        $this->publish_actions = $testcase['publish_actions'] ?? false;
        $this->dispatcher_actions = $testcase['dispatcher_actions'] ?? [];
        $this->graphics_actions = $testcase['actions'] ?? [];
    }

    public function getTestcaseLabel() {
        return $this->testcase_label;
    }

    /**
     * Gets the number of points this testcase is worth if $this->hidden is false, otherwise 0
     * @return int
     */
    public function getNonHiddenPoints() {
        return (!$this->isHidden()) ? $this->points : 0;
    }

    /**
     * Gets the number of points this testcase is worth if $this->hidden is false and $this->extra_credit is false,
     *  otherwise 0
     * @return int
     */
    public function getNonHiddenNonExtraCreditPoints() {
        return (!$this->isHidden() && !$this->isExtraCredit()) ? $this->points : 0;
    }

    /**
     * Gets whether this testcase is worth any points
     * @return bool
     */
    public function hasPoints() {
        return $this->points != 0;
    }

    /**
     * Gets whether the viewer should be able to view the message for a GradedAutogradingTestcase
     * @return bool
     */
    public function canViewTestcaseMessage() {
        return $this->view_testcase_message;
    }


    /** @internal */
    public function setIndex() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setName() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setDetails() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setPoints() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setExtraCredit() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setHidden() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }

    /** @internal */
    public function setViewTestcaseMessage() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingTestcase');
    }
}
