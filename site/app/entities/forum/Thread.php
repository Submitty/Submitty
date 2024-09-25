<?php

declare(strict_types=1);

namespace app\entities\forum;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\forum\ThreadRepository;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
#[ORM\Table(name: "threads")]
class Thread {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $id;

    public function getId(): int {
        return $this->id;
    }

    #[ORM\Column(type: Types::STRING)]
    protected string $title;

    #[ORM\Column(type: Types::STRING)]
    protected string $created_by;

    public function getCreatedBy() {
        return $created_by;
    }

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $pinned;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $deleted;

    #[ORM\ManyToOne(targetEntity: Thread::class, inversedBy: "merged_on_this")]
    #[ORM\JoinColumn(name: "merged_thread_id", referencedColumnName: "id")]
    protected ?Thread $merged_thread;

    /**
     * @var Collection<Thread>
     */
    #[ORM\OneToMany(mappedBy: "merged_thread", targetEntity: Thread::class)]
    protected Collection $merged_on_this;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "merged_threads")]
    #[ORM\JoinColumn(name: "merged_post_id", referencedColumnName: "id")]
    protected ?Post $merged_post;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $is_visible;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $status;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $lock_thread_date;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $pinned_expiration;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $announced;

    /**
     * @var Collection<Post>
     */
    #[ORM\OneToMany(mappedBy: "thread", targetEntity: Post::class)]
    protected Collection $posts;

    /**
     * @var Collection<Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: "threads")]
    #[ORM\JoinTable(name: "thread_categories")]
    #[ORM\JoinColumn(name: "thread_id", referencedColumnName: "id")]
    #[ORM\InverseJoinColumn(name: "category_id", referencedColumnName: "category_id")]
    protected Collection $categories;

    /**
     * @var Collection<ThreadAccess>
     */
    #[ORM\OneToMany(mappedBy: "thread", targetEntity: ThreadAccess::class)]
    protected Collection $viewers;

    /**
     * @var Collection<StudentFavorite>
     */
    #[ORM\OneToMany(mappedBy: "thread", targetEntity: StudentFavorite::class)]
    protected Collection $favorers;

    public function is_unread(string $user_id): bool {
        $last_viewed = $this->viewers->findFirst(function ($x) {
            return $user_id === $x->getUserId();
        })?->getTimestamp();
        if (is_null($last_viewed)) {
            return true;
        }
        return $last_viewed > max(array_merge(
            $this->posts->select(function ($x) {
                return $x->getTimestamp();
            })->toArray(),
            $this->posts->select(function ($x) {
                return $x->getHistory();
            })->select(function ($x) {
                return $x->getEditTimestamp();
            })->toArray()
        ));
    }
}
