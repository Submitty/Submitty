<?php

namespace app\entities;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class Course
 * Holds basic information about courses. Used on homepage.
 */
#[ORM\Entity]
#[ORM\Table(name: "course")]
class Course {
    /** @prop
     * @var string $term the term's code in which the course is taking place. */
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected $term;

    /** @prop
     * @var string $term_name the name of the term. aka "Long Term". */
    #[ORM\Column(type: Types::STRING)]
    protected $term_name;

    /** @prop
     * @var string $title the proper title of the course. */
    #[ORM\Column(type: Types::STRING)]
    protected $title;

    /** @prop
     * @var string $display_name the display name of the course. */
    #[ORM\Column(type: Types::STRING)]
    protected $display_name;

    /** @prop
     * @var int $user_group used to rank courses in homepage view. */
    #[ORM\Column(type: Types::INTEGER)]
    protected $user_group;

    /** @prop
     * @var string $registration_section for homepage view */
    #[ORM\Column(type: Types::STRING)]
    protected $registration_section;

    const ACTIVE_STATUS = 1;
    const ARCHIVED_STATUS = 2;

    /**
     * Course constructor.
     * @param array $details
     */
    public function __construct($details) {
        $this->term = $details['term'];
        $this->term_name = $details['term_name'];
        $this->title = $details['course'];
        $this->display_name = "";
        $this->user_group = $details['user_group'] ?? 3;
        $this->registration_section = $details['registration_section'] ?? null;
    }

    public function loadDisplayName(string $submitty_path) {
        $course_json_path = FileUtils::joinPaths(
            $submitty_path,
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
