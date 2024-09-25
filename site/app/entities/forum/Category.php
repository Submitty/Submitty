<?php

namespace app\entities\forum;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "categories_list")]
class Category {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $category_id;

    public function getId(): int {
        return $this->category_id;
    }

    #[ORM\Column(type: Types::STRING)]
    protected string $category_desc;

    public function getDescription(): string {
        return $this->category_desc;
    }

    #[ORM\Column(type: Types::INTEGER)]
    protected int $rank;

    #[ORM\Column(type: Types::STRING)]
    protected string $color;

    public function getColor(): string {
        return $this->color;
    }

    /**
     * @var Collection<Thread>
     */
    #[ORM\ManyToMany(targetEntity: Thread::class, mappedBy: "categories")]
    protected Collection $threads;
}
