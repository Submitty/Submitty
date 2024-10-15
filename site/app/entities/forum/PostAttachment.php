<?php

namespace app\entities\forum;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "forum_attachments")]
class PostAttachment {
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "attachments")]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName: "id", nullable: false)]
    protected Post $post;

    #[ORM\Column(type: TYPES::STRING)]
    protected string $file_name;

    #[ORM\Column(type: TYPES::INTEGER)]
    protected int $version_added;

    #[ORM\Column(type: TYPES::INTEGER)]
    protected int $version_deleted;

    public function getFileName(): string {
        return $this->file_name;
    }

    public function getVersionAdded(): int {
        return $this->version_added;
    }

    public function getVersionDeleted(): int {
        return $this->version_deleted;
    }

    public function isCurrent(): bool {
        return $this->version_deleted === 0;
    }
}
