<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use Exception;
use Doctrine\ORM\Mapping as ORM;
use app\libraries\plagiarism\PlagiarismUtils;

/**
 * Class OtherGradeable
 * @package app\entities\plagiarism
 * @ORM\Entity
 * @ORM\Table(name="lichen")
 */
class OtherGradeable {
    /* VARIABLES */

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $lichen_run_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $semester;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $course;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $gradeable;


    /* FUNCTIONS */

    /**
     * OtherGradeable constructor.
     * @param int $lichen_run_id
     * @param string $semester
     * @param string $course
     * @param string $gradeable
     * @throws Exception
     */
    public function __construct(int $lichen_run_id, string $semester, string $course, string $gradeable) {
        $this->lichen_run_id = $lichen_run_id;
        $this->setSemester($semester);
        $this->setCourse($course);
        $this->setGradeable($gradeable);
    }

    /**
     * @return int
     */
    public function getID(): int {
        return $this->lichen_run_id;
    }

    /**
     * @return string
     */
    public function getSemester(): string {
        return $this->semester;
    }

    /**
     * @param string $semester
     * @throws Exception
     */
    public function setSemester(string $semester): void {
        if (str_contains($semester, "..")) {
            throw new Exception("Error: Invalid path component '..'");
        }
        $this->semester = $semester;
    }

    /**
     * @return string
     */
    public function getCourse(): string {
        return $this->course;
    }

    /**
     * @param string $course
     * @throws Exception
     */
    public function setCourse(string $course): void {
        if (str_contains($course, "..")) {
            throw new Exception("Error: Invalid path component '..'");
        }
        $this->course = $course;
    }

    /**
     * @return string
     */
    public function getGradeable(): string {
        return $this->course;
    }

    /**
     * @param string $gradeable
     * @throws Exception
     */
    public function setGradeable(string $gradeable): void {
        if (str_contains($gradeable, "..")) {
            throw new Exception("Error: Invalid path component '..'");
        }
        $this->gradeable = $gradeable;
    }
}
