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
 * @method int getX1()
 * @method int getY1()
 * @method int getX2()
 * @method int getY2()
 *
 */
class Redaction extends AbstractModel implements \JsonSerializable {
    /** @property @var int The id of the redaction */
    protected $id = -1;
    /** @property @var int The page number of the redaction */
    protected $page_number = -1;
    /** @property @var int The x1 coordinate of the redaction */
    protected $x1 = -1;
    /** @property @var int The y1 coordinate of the redaction */
    protected $y1 = -1;
    /** @property @var int The x2 coordinate of the redaction */
    protected $x2 = -1;
    /** @property @var int The y2 coordinate of the redaction */
    protected $y2 = -1;
    /**
     * Redaction constructor.
     * @param array $details
     */
    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->id = $details['id'] ?? $this->id;
        $this->page_number = $details['page'];
        $this->x1 = $details['x1'];
        $this->y1 = $details['y1'];
        $this->x2 = $details['x2'];
        $this->y2 = $details['y2'];
    }

    public function jsonSerialize(): mixed {
        return [
            'page_number' => $this->page_number,
            "coordinates" => [$this->x1, $this->y1, $this->x2, $this->y2]
        ];
    }
}
