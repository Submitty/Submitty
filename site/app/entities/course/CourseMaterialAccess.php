<?php

namespace app\entities\course;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterialAccess
 * @package app\entities\course
 * @ORM\Entity
 * @ORM\Table(name="course_materials_access")
 */
class CourseMaterialAccess {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\course\CourseMaterial", inversedBy="accesses")
     * @var CourseMaterial
     */
    protected $course_material;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $user_id;

    /**
     * @ORM\Column(type="datetimetz")
     * @var DateTime
     */
    protected $timestamp;

    public function __construct(CourseMaterial $course_material, string $user_id, DateTime $timestamp) {
        $this->course_material = $course_material;
        $this->user_id = $user_id;
        $this->timestamp = $timestamp;
    }

    public function getUserId(): string {
        return $this->user_id;
    }
}
