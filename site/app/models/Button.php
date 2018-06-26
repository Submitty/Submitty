<?php

namespace app\models;
use app\libraries\Core;

/**
 * Represents a button to display on the page
 * @package app\models
 */
class Button extends AbstractModel {
    /** @var string $title */
    protected $title;
    /** @var string|null $subtitle */
    protected $subtitle;
    /** @var string $href */
    protected $href;
    /** @var string $class */
    protected $class;
    /** @var bool $disabled */
    protected $disabled;
    /** @var float|null $progress */
    protected $progress;

    /**
     * @param Core $core
     * @param array $details
     */
    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->title    = $details["title"] ?? "";
        $this->subtitle = $details["subtitle"] ?? null;
        $this->href     = $details["href"] ?? "";
        $this->class    = $details["class"] ?? "btn";
        $this->disabled = $details["disabled"] ?? false;
        $this->progress = $details["progress"] ?? null;
    }

}