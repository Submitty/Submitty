<?php

namespace app\entities;

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
     * @ORM\Column(type="string")
     * @var string
     */
    protected $course_material_id;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @var string
     */
    protected $section_id;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\CourseMaterial", inversedBy="sections")
     * @ORM\JoinColumn(name="course_material_id", referencedColumnName="path")
     * @var CourseMaterial
     */
    protected $course_material;

    /**
     * @return string
     */
    public function getSectionId(): string {
        return $this->section_id;
    }
}
