<?php

declare(strict_types=1);

namespace app\entities\forum;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\forum\CategoryRepository;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: "categories_list")]
class Category {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $category_id;

    #[ORM\Column(type: Types::STRING)]
    protected string $category_desc;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $rank;

    #[ORM\Column(type: Types::STRING)]
    protected string $color;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $visible_date;
    private ?int $diff = null;

    /**
     * @var Collection<Thread>
     */
    #[ORM\ManyToMany(targetEntity: Thread::class, mappedBy: "categories")]
    protected Collection $threads;

    public function __construct()
    {
        $this->visible_date = null;
    }

    public function getVisibleDate(): ?\DateTimeImmutable {
        return $this->visible_date;
    }

    public function getId(): int {
        return $this->category_id;
    }

    public function getDescription(): string {
        return $this->category_desc;
    }

    public function getColor(): string {
        return $this->color;
    }

    public function getDiff(): ?int {
        return $this->diff;
    }
}
