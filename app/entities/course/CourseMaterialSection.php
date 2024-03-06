<?php

namespace app\entities\course;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterialSection
 * @package app\entities
 */
#[ORM\Entity]
#[ORM\Table(name: "course_materials_sections")]
class CourseMaterialSection {
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $section_id;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CourseMaterial::class, inversedBy: "sections")]
    protected CourseMaterial $course_material;

    public function __construct(string $section_id, CourseMaterial $course_material) {
        $this->section_id = $section_id;
        $this->course_material = $course_material;
    }

    /**
     * @return string
     */
    public function getSectionId(): string {
        return $this->section_id;
    }
}
