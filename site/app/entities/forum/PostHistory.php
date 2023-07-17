<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "forum_posts_history")]
class PostHistory {
    #[ORM\ManyToOne(
        targetEntity: Post::class,
        inversedBy: "history"
    )]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName: "id")]
    protected Post $post;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $edit_author;

    #[ORM\Id]
    #[ORM\Column(type: Types::TEXT)]
    protected string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $edit_timestamp;
}
