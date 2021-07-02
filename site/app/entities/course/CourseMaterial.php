<?php

namespace app\entities\course;

use app\libraries\DateUtils;
use app\models\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterial
 * @package app\entities
 * @ORM\Entity
 * @ORM\Table(name="course_materials")
 */
class CourseMaterial {
    const FILE = 0;
    const LINK = 1;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $path;

    /**
     * @ORM\Column(type="smallint")
     * @var int
     */
    protected $type;

    /**
     * @ORM\Column(type="datetimetz")
     * @var \DateTime
     */
    protected $release_date;

    /**
     * @ORM\Column(type="boolean")
     * @var boolean
     */
    protected $hidden_from_students;

    /**
     * @ORM\Column(type="float")
     * @var float
     */
    protected $priority;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\course\CourseMaterialSection", mappedBy="course_material", fetch="EAGER", cascade={"persist"}, orphanRemoval=true)
     * @var Collection<CourseMaterialSection>
     */
    protected $sections;

    public function __construct(array $details) {
        $this->setType($details['type']);
        $this->setPath($details['path']);
        $this->setReleaseDate($details['release_date']);
        $this->setHiddenFromStudents($details['hidden_from_students']);
        $this->setPriority($details['priority']);
        $this->sections = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getSections(): Collection {
        return $this->sections;
    }

    public function getPath(): string {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    public function isFile(): bool {
        return $this->type === self::FILE;
    }

    public function isLink(): bool {
        return $this->type === self::LINK;
    }

    /**
     * @return float
     */
    public function getPriority(): float {
        return $this->priority;
    }

    /**
     * @return \DateTime
     */
    public function getReleaseDate(): \DateTime {
        return $this->release_date;
    }

    /**
     * @return bool
     */
    public function isHiddenFromStudents(): bool {
        return $this->hidden_from_students;
    }

    public function isSectionAllowed(User $user): bool {
        foreach ($this->sections->getValues() as $course_material_section) {
            if ($user->getRegistrationSection() === $course_material_section->getSectionId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \DateTime $release_date
     */
    public function setReleaseDate(\DateTime $release_date): void {
        $this->release_date = $release_date;
    }

    /**
     * @param bool $hidden_from_students
     */
    public function setHiddenFromStudents(bool $hidden_from_students): void {
        $this->hidden_from_students = $hidden_from_students;
    }

    /**
     * @param float $priority
     */
    public function setPriority(float $priority): void {
        $this->priority = $priority;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void {
        $this->path = $path;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void {
        $this->type = $type;
    }

    public function addSection(CourseMaterialSection $section): void {
        $this->sections[] = $section;
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }
}
