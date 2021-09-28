<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="forum_posts_history")
 */
class PostHistory {
    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\forum\Post", inversedBy="history")
     * @ORM\JoinColumn(name="post_id", referencedColumnName="id")
     * @var Post
     */
    protected $post;

    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     * @var string
     */
    protected $edit_author;

    /**
     * @ORM\Column(type="text")
     * @ORM\Id
     * @var string
     */
    protected $content;

    /**
     * @ORM\Column(type="datetimetzmicro")
     * @var DateTime
     */
    protected $edit_timestamp;
}
