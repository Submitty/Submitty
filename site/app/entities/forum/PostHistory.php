<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "forum_posts_history")]
class PostHistory {
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "history")]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName: "id", nullable: false)]
    protected Post $post;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $edit_author;

    #[ORM\Id]
    #[ORM\Column(type: Types::TEXT)]
    protected string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $edit_timestamp;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $version_id;

    public function getEditAuthor(): string {
        return $this->edit_author;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function getEditTimestamp(): DateTime {
        return $this->edit_timestamp;
    }

    /**
     * @return Collection<PostAttachment>
     */
    public function getAttachments(): Collection {
        return $this->post->getAttachments()->filter(function ($x) {
            return $x->getVersionAdded() <= $this->version_id && ($x->isCurrent() || $this->version_id < $x->getVersionDeleted());
        });
    }
}
