<?php

namespace app\entities\course;

use app\libraries\Core;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterialSection
 * @package app\entities
 * @ORM\Entity
 * @ORM\Table(name="course_materials_sections")
 */
class CourseMaterialSection {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $course_material_id;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @var string
     */
    protected $section_id;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\course\CourseMaterial", inversedBy="sections")
     * @ORM\JoinColumn(name="course_material_id", referencedColumnName="id")
     * @var CourseMaterial
     */
    protected $course_material;

    public function __construct(string $section_id, CourseMaterial $course_material) {
        $this->section_id = $section_id;
        $this->course_material = $course_material;
        $this->course_material_id = $course_material->getId();
    }

    /**
     * @return string
     */
    public function getSectionId(): string {
        return $this->section_id;
    }
}
