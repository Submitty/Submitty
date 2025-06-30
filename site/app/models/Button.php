<?php

namespace app\models;

use app\libraries\Core;

/**
 * Represents a button to display on the page
 * @package app\models
 */
class Button extends AbstractModel {
    /** @prop
     * @var string|null $title */
    protected $title;
    /** @prop
     * @var string|null $subtitle */
    protected $subtitle;
    /** @prop
     * @var string|null $name */
    protected $name;
    /** @prop
     * @var \DateTime|null A DateTime object representing the time for this Button.  For example if this was a
     * grade button then $date may represent the date and time grading begins or ends */
    protected $date;
    /** @prop
     * @var string|null $href */
    protected $href;
    /** @prop
     * @var string|null $onclick */
    protected $onclick;
    /** @prop
     * @var string $class */
    protected $class;
    /** @prop
     * @var string|null $id */
    protected $id;
    /** @prop
     * @var bool $disabled */
    protected $disabled;
    /** @prop
     * @var string $prerequisite */
    protected $prerequisite;
    /** @prop
     * @var float|null $progress */
    protected $progress;
    /** @prop
     * @var bool $title_on_hover */
    protected $title_on_hover;
    /** @prop
     * @var string|null $aria_label */
    protected $aria_label;
    /** @prop
     * @var string|null $badge */
    protected $badge;
    /** @prop
     * @var string|null $icon */
    protected $icon;
    /** @prop
     * @var string|null $prefix */
    protected $prefix;

    /**
     * @param Core $core
     * @param array $details
     */
    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->title    = $details["title"] ?? null;
        $this->subtitle = $details["subtitle"] ?? null;
        $this->name     = $details["name"] ?? null;
        $this->date     = $details["date"] ?? null;
        $this->href     = $details["href"] ?? null;
        $this->onclick  = $details["onclick"] ?? null;
        $this->class    = $details["class"] ?? "btn";
        $this->id       = $details["id"] ?? null;
        $this->disabled = $details["disabled"] ?? false;
        $this->prerequisite = $details["prerequisite"] ?? null;
        $this->progress = $details["progress"] ?? null;
        if ($this->progress !== null) {
            $this->progress = max(0, min(100, floatval($this->progress)));
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
    public function getTitle(): ?string {
        return $this->title;
    }

    public function getSubtitle(): ?string {
        return $this->subtitle;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function getDate(): ?\DateTime {
        return $this->date;
    }

    public function getHref(): ?string {
        return $this->href;
    }

    public function getOnclick(): ?string {
        return $this->onclick;
    }

    public function getClass(): string {
        return $this->class;
    }
    public function getId(): ?string {
        return $this->id;
    }
    public function isDisabled(): bool {
        return $this->disabled;
    }
    public function getPrerequisite(): ?string {
        return $this->prerequisite;
    }
    public function isTitleOnHover(): bool {
        return $this->title_on_hover;
    }
    public function getProgress(): ?float {
        return $this->progress;
    }
    public function getAriaLabel(): ?string {
        return $this->aria_label;
    }
    public function getBadge(): ?string {
        return $this->badge;
    }
    public function getIcon(): ?string {
        return $this->icon;
    }

    public function setTitle(string $title): void {
        $this->title = $title;
    }

    public function setSubtitle(?string $subtitle): void {
        $this->subtitle = $subtitle;
    }

    public function setDate(?\DateTime $date): void {
        $this->date = $date;
    }

    public function setHref(?string $href): void {
        $this->href = $href;
    }

    public function setOnclick(?string $on_click): void {
        $this->onclick = $on_click;
    }

    public function setClass(string $class): void {
        $this->class = $class;
    }
    public function setId(?string $id): void {
        $this->id = $id;
    }
    public function setDisabled(bool $disabled): void {
        $this->disabled = $disabled;
    }
    public function setTitleOnHover(bool $titleOnHover): void {
        $this->title_on_hover = $titleOnHover;
    }
    public function setProgress(?float $progress): void {
        $this->progress = $progress;
    }
    public function setAriaLabel(?string $ariaLabel): void {
        $this->aria_label = $ariaLabel;
    }
    public function setBadge(?string $badge): void {
        $this->badge = $badge;
    }
    public function setIcon(?string $icon): void {
        $this->icon = $icon;
    }
}
