<?php

namespace app\entities\course;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterialAccess
 * @package app\entities\course
 */
#[ORM\Entity]
#[ORM\Table(name: "course_materials_access")]
class CourseMaterialAccess {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: CourseMaterial::class, inversedBy: "accesses")]
    protected CourseMaterial $course_material;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $timestamp;

    public function __construct(CourseMaterial $course_material, string $user_id, DateTime $timestamp) {
        $this->course_material = $course_material;
        $this->user_id = $user_id;
        $this->timestamp = $timestamp;
    }


}


