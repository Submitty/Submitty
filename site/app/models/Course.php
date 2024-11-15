<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
 * @method string getTerm()
 * @method string getTermName()
 * @method string getTitle()
 * @method string getDisplayName()
 * @method int getUserGroup()
 */
class Course extends AbstractModel {
    /** @prop
     * @var string $term the term's code in which the course is taking place. */
    protected $term;
    /** @prop
     * @var string $term_name the name of the term. aka "Long Term". */
    protected $term_name;
    /** @prop
     * @var string $title the proper title of the course. */
    protected $title;
    /** @prop
     * @var string $display_name the display name of the course. */
    protected $display_name;
    /** @prop
     * @var int $user_group used to rank courses in homepage view. */
    protected $user_group;
    /** @prop
     * @var string $registration_section for homepage view */
    protected $registration_section;

    const ACTIVE_STATUS = 1;
    const ARCHIVED_STATUS = 2;

    /**
     * Course constructor.
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->term = $details['term'];
        $this->term_name = $details['term_name'];
        $this->title = $details['course'];
        $this->display_name = "";
        $this->user_group = $details['user_group'] ?? 3;
        $this->registration_section = $details['registration_section'] ?? null;
    }

    public function loadDisplayName() {
        $course_json_path = FileUtils::joinPaths(
            $this->core->getConfig()->getSubmittyPath(),
            "courses",
            $this->term,
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

    public function getLongTerm() {
        return $this->term_name;
    }

    public function getCapitalizedTitle() {
        return strtoupper($this->title);
    }

    public function getCourseInfo() {
        return [
            "semester" => $this->term,
            "title" => $this->title,
            "display_name" => $this->display_name,
            "display_semester" => $this->term_name,
            "user_group" => $this->user_group,
            "registration_section" => $this->registration_section
        ];
    }
}
