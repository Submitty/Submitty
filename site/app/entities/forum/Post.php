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

    /**
     * @var Collection<Thread>
     */
    #[ORM\OneToMany(mappedBy: "merged_post", targetEntity: Thread::class)]
    protected Collection $merged_threads;

    #[ORM\ManyToOne(targetEntity: Thread::class, inversedBy: "posts")]
    #[ORM\JoinColumn(name: "thread_id", referencedColumnName: "id", nullable: false)]
    protected Thread $thread;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "children")]
    #[ORM\JoinColumn(name: "parent_id", referencedColumnName: "id")]
    protected ?Post $parent;

    /**
     * @var Collection<Post>
     */
    #[ORM\OneToMany(mappedBy: "parent", targetEntity: Post::class)]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: "posts")]
    #[ORM\JoinColumn(name: "author_user_id", referencedColumnName: "user_id", nullable: false)]
    protected UserEntity $author;

    #[ORM\Column(type: Types::TEXT)]
    protected string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $timestamp;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $anonymous;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $deleted;

    #[ORM\Column(type: Types::STRING)]
    protected string $endorsed_by;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $type;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $has_attachment;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $render_markdown;

    /**
     * @var Collection<PostHistory>
     */
    #[ORM\OneToMany(mappedBy: "post", targetEntity: PostHistory::class)]
    protected Collection $history;

    /**
     * @var Collection<PostAttachment>
     */
    #[ORM\OneToMany(mappedBy: "post", targetEntity: PostAttachment::class)]
    protected Collection $attachments;

    /**
     * @var Collection<UserEntity>
     */
    #[ORM\ManyToMany(targetEntity: UserEntity::class, inversedBy: "upducks")]
    #[ORM\JoinTable(name: "forum_upducks")]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName:"id")]
    #[ORM\InverseJoinColumn(name:"user_id", referencedColumnName:"user_id")]
    protected Collection $upduckers;

    protected int $reply_level = 1;

    /**
     * Doctrine ORM does not use constructors, instead filling properties from database.
     * We are free to make constructors for "empty" or "junk" posts.
     */

    public function __construct(Thread $empty_thread) {
        $this->content = '';
        $this->render_markdown = false;
        $this->author = $empty_thread->getAuthor();
        $this->thread = $empty_thread;
        $this->deleted = false;
        $this->anonymous = true;
        $this->timestamp = new DateTime("0000-00-00");
        $this->id = -1;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getThread(): Thread {
        return $this->thread;
    }

    public function getParent(): ?Post {
        return $this->parent;
    }

    /**
     * @return Collection<Post>
     */
    public function getChildren(): Collection {
        return $this->children;
    }

    public function getAuthor(): UserEntity {
        return $this->author;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }

    public function isAnonymous(): bool {
        return $this->anonymous;
    }

    public function isDeleted(): bool {
        return $this->deleted;
    }

    public function isRenderMarkdown(): bool {
        return $this->render_markdown;
    }

    /**
     * @return Collection<PostHistory>
     */
    public function getHistory(): Collection {
        return $this->history;
    }

    /**
     * @return Collection<PostAttachment>
     */
    public function getAttachments(): Collection {
        return $this->attachments;
    }

    /**
     * @return Collection<UserEntity>
     */
    public function getUpduckers(): Collection {
        return $this->upduckers;
    }

    public function getReplyLevel(): int {
        return $this->reply_level;
    }
    public function setReplyLevel(int $new): void {
        $this->reply_level = $new;
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
