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

    #[ORM\Column(type: Types::STRING)]
    protected string $category_desc;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $rank;

    #[ORM\Column(type: Types::STRING)]
    protected string $color;

    /**
     * @var Collection<Thread>
     */
    #[ORM\ManyToMany(targetEntity: Thread::class, mappedBy: "categories")]
    protected Collection $threads;

    public function getId(): int {
        return $this->category_id;
    }

    public function getDescription(): string {
        return $this->category_desc;
    }

    public function getColor(): string {
        return $this->color;
    }

    /**
     * @param Collection<Category> $c1 Collection of unique categories.
     * @param Collection<Category> $c2 Collection of unique categories.
     * @return bool true iff collections contain the same categories
     */
    public static function areCollectionsEqual(Collection $c1, Collection $c2): bool {
        if (count($c1) !== count($c2)) {
            return false;
        }
        foreach ($c1 as $cat) {
            // Doctrine ensures only one instance of an entity in memory, so reference equality works. 
            if (!$c2->contains($cat)) {
                return false;
            }
        }
        return true;
    }
}
