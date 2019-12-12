<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
 * @method string getSemester()
 * @method string getTitle()
 * @method string getDisplayName()
  */
class Course extends AbstractModel {
     
    /** @property @var string the semester in which the course is taking place." */
    protected $semester;
    /** @property @var the proper title of the course. */
    protected $title;
    /** @property @var the display name of the course */
    protected $display_name;

    /**
     * Course constructor.
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->semester = $details['semester'];
        $this->title = $details['course'];
        $this->display_name = "";
    }

    public function loadDisplayName() {
        $course_json_path = FileUtils::joinPaths(
            $this->core->getConfig()->getSubmittyPath(),
            "courses",
            $this->semester,
            $this->title,
            "config",
            "config.json"
        );
        if (file_exists($course_json_path) && is_readable($course_json_path)) {
            $config = json_decode(file_get_contents($course_json_path), true);
            if (isset($config['course_details']) && isset($config['course_details']['course_name'])) {
                $this->display_name = $config['course_details']['course_name'];
                return true;
            }
        }
        return false;
    }

    public function getLongSemester() {
        if (strlen($this->semester) == 3) {
            if (strtolower($this->semester[0]) === 'f') {
                return "Fall 20" . substr($this->semester, 1, 2);
            }
            elseif (strtolower($this->semester[0]) === 's') {
                return "Spring 20" . substr($this->semester, 1, 2);
            }
            elseif (strtolower($this->semester[0]) === 'u') {
                return "Summer 20" . substr($this->semester, 1, 2);
            }
        }
        return $this->semester;
    }

    public function getCapitalizedTitle() {
        return strtoupper($this->title);
    }

    public function getCourseInfo() {
        return [
            "semester" => $this->semester,
            "title" => $this->title,
            "display_name" => $this->display_name,
            "display_semester" => $this->getLongSemester()
        ];
    }
}
