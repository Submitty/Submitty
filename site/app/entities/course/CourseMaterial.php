<?php

declare(strict_types=1);

namespace app\entities\course;

use app\repositories\course\CourseMaterialRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CourseMaterial
 * @package app\entities
 */
#[ORM\Entity(repositoryClass: CourseMaterialRepository::class)]
#[ORM\Table(name: "course_materials")]
class CourseMaterial {
    const FILE = 0;
    const LINK = 1;
    const DIR = 2;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $path;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $type;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $release_date;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $hidden_from_students;

    #[ORM\Column(type: Types::FLOAT)]
    protected float $priority;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $on_calendar;

    #[ORM\Column(type: Types::STRING)]
    protected string $gradeable;    

    /**
     * @var Collection<CourseMaterialAccess>
     */
    #[ORM\OneToMany(
        mappedBy: "course_material",
        targetEntity: CourseMaterialAccess::class,
        cascade: ["persist"],
        fetch: "EAGER",
        orphanRemoval: true
    )]
    protected Collection $accesses;

    /**
     * @var Collection<CourseMaterialSection>
     */
    #[ORM\OneToMany(
        mappedBy: "course_material",
        targetEntity: CourseMaterialSection::class,
        cascade: ["persist"],
        fetch: "EAGER",
        orphanRemoval: true
    )]
    protected Collection $sections;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $url;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $title;

    public function __construct(int $type, string $path, \DateTime $release_date, bool $hidden_from_students, float $priority, ?string $url, ?string $title, ?bool $onCalender, ?string $gradeableName) {
        $this->setType($type);
        $this->setPath($path);
        $this->setReleaseDate($release_date);
        $this->setHiddenFromStudents($hidden_from_students);
        $this->setPriority($priority);
        $this->sections = new ArrayCollection();
        $this->url = $url;
        $this->title = $title;
        $this->on_calendar = $on_calendar;
        $this->gradeable = $gradeableName;
    }

    /**
     * @return Collection<CourseMaterialSection>
     */
    public function getSections(): Collection {
        return $this->sections;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getType(): int {
        return $this->type;
    }

    public function isFile(): bool {
        return $this->type === self::FILE;
    }

    public function isLink(): bool {
        return $this->type === self::LINK;
    }

    public function isDir(): bool {
        return $this->type === self::DIR;
    }

    public function getPriority(): float {
        return $this->priority;
    }

    public function getReleaseDate(): \DateTime {
        return $this->release_date;
    }

    public function isHiddenFromStudents(): bool {
        return $this->hidden_from_students;
    }

    public function isSectionAllowed(string $section): bool {
        if ($this->sections->count() === 0) {
            return true;
        }
        foreach ($this->sections->getValues() as $course_material_section) {
            if ($section === $course_material_section->getSectionId()) {
                return true;
            }
        }
        return false;
    }

    public function isOnCalendar(): bool {
        return $this->on_calendar;
    }

    public function getGradeable(): string {
        return $this->gradeable;
    }

    public function setReleaseDate(\DateTime $release_date): void {
        $this->release_date = $release_date;
    }

    public function setHiddenFromStudents(bool $hidden_from_students): void {
        $this->hidden_from_students = $hidden_from_students;
    }

    public function setPriority(float $priority): void {
        $this->priority = $priority;
    }

    public function setPath(string $path): void {
        $this->path = $path;
    }

    public function setType(int $type): void {
        $this->type = $type;
    }

    public function addSection(CourseMaterialSection $section): void {
        $this->sections[] = $section;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function removeSection(CourseMaterialSection $section): void {
        $this->sections->removeElement($section);
    }

    public function addAccess(CourseMaterialAccess $access): void {
        $this->accesses[] = $access;
    }

    public function userHasViewed(string $user_id): bool {
        return $this->accesses->filter(function (CourseMaterialAccess $courseMaterialAccess) use ($user_id) {
                return $courseMaterialAccess->getUserId() === $user_id;
        })->count() > 0;
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function getTitle(): ?string {
        return $this->title;
    }

    public function setUrl(string $url): void {
        $this->url = $url;
    }

    public function setTitle(string $title): void {
        $this->title = $title;
    }
    public function setIsOnCalendar(bool $value): bool {
        $this->on_calendar = $value;
    }

    public function setGradeable(string $gradeableString): string {
        $this->gradeable = $gradeableString;
    }
}
