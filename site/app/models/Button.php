<?php

namespace app\models;

use app\libraries\Core;

/**
 * Represents a button to display on the page
 * @package app\models
 * @method string getTitle()
 * @method string|null getSubtitle()
 * @method \DateTime|null getDate()
 * @method string|null getHref()
 * @method string|null getOnclick()
 * @method string getClass()
 * @method string|null getId()
 * @method bool isDisabled()
 * @method string getPrerequisite()
 * @method bool isTitleOnHover()
 * @method float|null getProgress()
 * @method string|null getAriaLabel()
 * @method string|null getBadge()
 * @method string|null getIcon()
 * @method bool getPrefix()
 *
 * @method void setTitle(string $title)
 * @method void setSubtitle(string|null $subtitle)
 * @method void setDate(\DateTime|null $date)
 * @method void setHref(string|null $href)
 * @method void setOnclick(string|null $on_click)
 * @method void setClass(string $class)
 * @method void setId(string|null $id)
 * @method void setDisabled(bool $disabled)
 * @method void setTitleOnHover(bool $titleOnHover)
 * @method void setProgress(float|null $progress)
 * @method void setAriaLabel(string|null $ariaLabel)
 * @method void setBadge(string|null $badge)
 * @method void setIcon(string|null $icon)
 */
class Button extends AbstractModel {
    /** @var string|null $title */
    protected $title;
    /** @var string|null $subtitle */
    protected $subtitle;
    /** @var \DateTime|null A DateTime object representing the time for this Button.  For example if this was a
     * grade button then $date may represent the date and time grading begins or ends */
    protected $date;
    /** @var string|null $href */
    protected $href;
    /** @var string|null $onclick */
    protected $onclick;
    /** @var string $class */
    protected $class;
    /** @var string|null $id */
    protected $id;
    /** @var bool $disabled */
    protected $disabled;
    /** @var string $prerequisite */
    protected $prerequisite;
    /** @var float|null $progress */
    protected $progress;
    /** @var bool $title_on_hover */
    protected $title_on_hover;
    /** @var string|null $aria_label */
    protected $aria_label;
    /** @var string|null $badge */
    protected $badge;
    /** @var string|null $icon */
    protected $icon;
     /** @var string|null $prefix */
    protected $prefix;

    /**
     * @param Core $core
     * @param array $details
     */
    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->title    = $details["title"] ?? null;
        $this->subtitle = $details["subtitle"] ?? null;
        $this->date     = $details["date"] ?? null;
        $this->href     = $details["href"] ?? null;
        $this->onclick  = $details["onclick"] ?? null;
        $this->class    = $details["class"] ?? "btn";
        $this->id       = $details["id"] ?? null;
        $this->disabled = $details["disabled"] ?? false;
        $this->prerequisite = $details["prerequisite"] ?? null;
        $this->progress = $details["progress"] ?? null;
        if ($this->progress !== null) {
            $this->progress = floatval($this->progress);
        }
        $this->title_on_hover = $details["title_on_hover"] ?? false;
        $this->aria_label = $details["aria_label"] ?? null;
        $this->badge = $details["badge"] ?? null;
        $this->icon = $details["icon"] ?? null;
        $this->prefix = $details["prefix"] ?? "fa";
    }

    /**
     * checks whether a button has on onclick affect
     * @return bool
     */
    public function hasOnclick() {
        return !($this->getOnclick() == null);
    }
}
