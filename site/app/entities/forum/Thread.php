<?php

declare(strict_types=1);

namespace app\entities\forum;

use DateInterval;
use DateTime;
use Doctrine\Common\Collections\Collection;
use app\entities\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\forum\ThreadRepository;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
#[ORM\Table(name: "threads")]
class Thread {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $title;

    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: "threads")]
    #[ORM\JoinColumn(name: "created_by", referencedColumnName: "user_id", nullable: false)]
    protected UserEntity $author;

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

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    protected ?DateTime $lock_thread_date;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $pinned_expiration;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    protected ?DateTime $announced;

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

    public function getId(): int {
        return $this->id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getAuthor(): UserEntity {
        return $this->author;
    }

    public function isDeleted(): bool {
        return $this->deleted;
    }

    public function getMergedThread(): ?Thread {
        return $this->merged_thread;
    }

    public function isMergedThread(): bool {
        return ($this->merged_thread?->getId() ?? -1) !== -1;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function setStatus(int $newStatus): void {
        $this->status = $newStatus;
    }

    public function getLockDate(): ?DateTime {
        return $this->lock_thread_date;
    }

    public function isLocked(): bool {
        return $this->lock_thread_date !== null && $this->lock_thread_date < new DateTime("now");
    }

    public function getPinnedExpiration(): DateTime {
        return $this->pinned_expiration;
    }

    public function isPinned(): bool {
        return $this->pinned_expiration > new DateTime("now");
    }

    public function isPinnedExpiring(): bool {
        return $this->pinned_expiration <= (new DateTime("now"))->add(DateInterval::createFromDateString("7 days"));
    }

    public function isAnnounced(): bool {
        return !is_null($this->announced);
    }

    /**
     * @return Collection<Post>
     */
    public function getPosts(): Collection {
        return $this->posts;
    }

    /**
     * @return Collection<Category>
     */
    public function getCategories(): Collection {
        return $this->categories;
    }
    public function isUnread(string $user_id): bool {
        return !$this->getNewPosts($user_id)->isEmpty();
    }

    /**
     * @return Collection<Post>
     */
    public function getNewPosts(string $user_id): Collection {
        $last_view = $this->viewers->filter(function ($x) use ($user_id) {
            return $user_id === $x->getUserId();
        })->first();

        if ($last_view === false) {
            return $this->posts;
        }
        return $this->posts->filter(function ($x) use ($last_view) {
            return $x->isUnread($last_view);
        });
    }

    public function isFavorite(string $user_id): bool {
        return $this->favorers->map(function ($x) {
            return $x->getUserId();
        })->contains($user_id);
    }

    public function getFirstPost(): Post|false {
        return $this->posts->filter(function ($x) {
            return $x->getParent()->getId() === -1;
        })->first();
    }

    public function getSumUpducks(): int {
        $sum_upducks = 0;
        foreach ($this->getPosts() as $post) {
            $sum_upducks += count($post->getUpduckers());
        }
        return $sum_upducks;
    }
}
