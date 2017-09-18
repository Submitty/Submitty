<?php

namespace app\models;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\IniParser;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
 * @method string getSemester()
 * @method string getTitle()
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

    public function loadDisplayName($submitty_path){
        $course_ini_path = FileUtils::joinPaths($submitty_path, "courses", $this->semester, $this->title, "config", "config.ini");
        if (file_exists($course_ini_path) && is_readable ($course_ini_path)) {
            $config = IniParser::readFile($course_ini_path);
            if (isset($config['course_details']['course_name'])) {
                $this->display_name = $config['course_details']['course_name'];            
            }
        }
    }
}
