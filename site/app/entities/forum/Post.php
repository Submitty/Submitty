<?php

declare(strict_types=1);

namespace app\entities\forum;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\forum\PostRepository;
use app\entities\UserEntity;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: "posts")]
class Post {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $id;

    public function getId(): int {
        return $this->id;
    }
    /**
     * @var Collection<Thread>
     */
    #[ORM\OneToMany(mappedBy: "merged_post", targetEntity: Thread::class)]
    protected Collection $merged_threads;

    #[ORM\ManyToOne(targetEntity: Thread::class, inversedBy: "posts")]
    #[ORM\JoinColumn(name: "thread_id", referencedColumnName: "id", nullable: false)]
    protected Thread $thread;

    public function getThread(): Thread {
        return $this->thread;
    }

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "children")]
    #[ORM\JoinColumn(name: "parent_id", referencedColumnName: "id")]
    protected ?Post $parent;
    
    public function getParent(): ?Post {
        return $this->parent;
    }

    /**
     * @var Collection<Post>
     */
    #[ORM\OneToMany(mappedBy: "parent", targetEntity: Post::class)]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: "posts")]
    #[ORM\JoinColumn(name: "author_user_id", referencedColumnName: "user_id")]
    protected UserEntity $author;

    public function getAuthor(): UserEntity {
        return $this->author;
    }

    #[ORM\Column(type: Types::TEXT)]
    protected string $content;

    public function getContent(): string {
        return $this->content;
    }

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $timestamp;

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $anonymous;

    public function isAnonymous(): bool {
        return $this->anonymous;
    }

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $deleted;

    public function isDeleted(): bool {
        return $this->deleted;
    }

    #[ORM\Column(type: Types::STRING)]
    protected string $endorsed_by;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $type;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $has_attachment;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $render_markdown;

    public function isRenderMarkdown(): bool {
        return $this->render_markdown;
    }

    /**
     * @var Collection<PostHistory>
     */
    #[ORM\OneToMany(mappedBy: "post", targetEntity: PostHistory::class)]
    protected Collection $history;

    /**
     * @return Collection<PostHistory>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    /**
     * @var Collection<PostAttachment>
     */
    #[ORM\OneToMany(mappedBy: "post", targetEntity: PostAttachment::class)]
    protected Collection $attachments;

    /**
     * @return Collection<PostAttachment>
     */
    public function getAttachments(): Collection {
        return $this->attachments;
    }

    /**
     * Doctrine ORM does not use constructors, instead filling properties from database.
     * We are free to make constructors for "empty" or "junk" posts.
     */ 
    
    public function __construct() {
        $this->content = '';
        $this->render_markdown = false;
    }

    public function isUnread(ThreadAccess $view): bool {
        if ($this->history->isEmpty()) {
            return $view->getTimestamp() < $this->getTimestamp();
        }
        return $view->getTimestamp() < max($this->history->map(function ($x) {
            return $x->getEditTimestamp();
        })->toArray());
    }
}
