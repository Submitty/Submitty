<?php

namespace app\models\gradeable;

use app\models\AbstractModel;
use app\libraries\Core;

/**
 * Class Redaction
 * @package app\models\gradeable
 *
 * All data describing the configuration of a gradeable redaction
 *
 * @method int getId()
 * @method int getPageNumber()
 * @method float getX1()
 * @method float getY1()
 * @method float getX2()
 * @method float getY2()
 *
 */
class Redaction extends AbstractModel implements \JsonSerializable {
    /** @prop
     * @var int The page number of the redaction */
    protected $page_number;
    /** @prop
     * @var float The x1 coordinate of the redaction */
    protected $x1;
    /** @prop
     * @var float The y1 coordinate of the redaction */
    protected $y1;
    /** @prop
     * @var float The x2 coordinate of the redaction */
    protected $x2;
    /** @prop
     * @var float The y2 coordinate of the redaction */
    protected $y2;

    public function __construct(Core $core, int $page_number, float $x1, float $y1, float $x2, float $y2) {
        parent::__construct($core);
        $this->page_number = $page_number;
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
    }

    public function jsonSerialize(): mixed {
        return [
            'page_number' => $this->page_number,
            "coordinates" => [$this->x1, $this->y1, $this->x2, $this->y2]
        ];
    }
}
