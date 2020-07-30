<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
 * @method string getSemester()
 * @method string getSemesterName()
 * @method string getTitle()
 * @method string getDisplayName()
 * @method int getUserGroup()
 */
class Course extends AbstractModel {

    /** @property string $semester the semester (or term) code in which the course is taking place. */
    protected $semester;
    /** @property string $semester_name the name of the semester (or term). aka "Long Semester". */
    protected $semester_name;
    /** @property string $title the proper title of the course. */
    protected $title;
    /** @property string $display_name the display name of the course. */
    protected $display_name;
    /** @property int $user_group used to rank courses in homepage view. */
    protected $user_group;

    /**
     * Course constructor.
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->semester = $details['semester'];
        $this->semester_name = $details['term_name'];
        $this->title = $details['course'];
        $this->display_name = "";
        $this->user_group = $details['user_group'] ?? 3;
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
        return $this->semester_name;
    }

    public function getCapitalizedTitle() {
        return strtoupper($this->title);
    }

    public function getCourseInfo() {
        return [
            "semester" => $this->semester,
            "title" => $this->title,
            "display_name" => $this->display_name,
            "display_semester" => $this->semester_name,
            "user_group" => $this->user_group
        ];
    }
}
