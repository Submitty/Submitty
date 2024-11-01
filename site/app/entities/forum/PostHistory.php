<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use app\entities\UserEntity;

#[ORM\Entity]
#[ORM\Table(name: "forum_posts_history")]
class PostHistory {
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "history")]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName: "id", nullable: false)]
    #[ORM\Id]
    protected Post $post;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name:"edit_author", referencedColumnName:"user_id", nullable: false)]
    protected UserEntity $edit_author;

    #[ORM\Column(type: Types::TEXT)]
    protected string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $edit_timestamp;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    protected int $version_id;

    public function getEditAuthor(): UserEntity {
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
