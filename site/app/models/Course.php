<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
  */
class Course extends AbstractModel {
     
    /** @property @var string the semester in which the course is taking place." */
    protected $semester;
    /** @property @var the title of the course. */
    protected $title;

    /**
     * Course constructor.
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->semester = $details['semester'];
        $this->title = $details['course'];
    }

    /**
     * Returns the course's semester.
     * @return string
    */
    public function getSemester() {
        return $this->semester;
    }

    /**
     * Returns the course's title.
     * @return string
    */
    public function getTitle() {
        return $this->title;
    }
}
