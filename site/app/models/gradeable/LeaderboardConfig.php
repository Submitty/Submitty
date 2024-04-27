<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class LeaderboardConfig
 * @package app\models\gradeable
 *
 * @method int getTopVisibleStudents()
 * @method string getTitle()
 * @method string getDescription()
 * @method string getTag()
 */
class LeaderboardConfig extends AbstractModel {
    /** @prop
     * @var int The number of top students to show, 0 for all students */
    protected $top_visible_students;
    /** @prop
     * @var string The human readable title */
    protected $title;
    /** @prop
     * @var string The human readable description */
    protected $description;
    /** @prop
     * @var string A unique tag for the leaderboard */
    protected $tag;


    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        // Was there actually a config file to read from
        if ($details === []) {
            throw new \InvalidArgumentException('Provided details were blank or null');
        }

        $this->top_visible_students = $details["top_visible_students"];
        $this->title = $details["title"];
        $this->description = $details["description"] ?? "";
        $this->tag = $details["tag"];
    }
}
