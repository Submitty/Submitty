<?php

declare(strict_types=1);

namespace app\entities\forum;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="posts")
 */
class Post {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\forum\Thread", mappedBy="merged_post")
     * @var Collection<Thread>
     */
    protected $merged_threads;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\forum\Thread", inversedBy="posts")
     * @ORM\JoinColumn(name="thread_id", referencedColumnName="id")
     * @var Thread
     */
    protected $thread;

    /**
     * @ORM\ManyToOne(targetEntity="Post", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     * @var Post
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Post", mappedBy="parent")
     * @var Collection<Post>
     */
    protected $children;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $author_user_id;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    protected $content;

    /**
     * @ORM\Column(type="datetimetzmicro")
     * @var DateTime
     */
    protected $timestamp;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $anonymous;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $deleted;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $endorsed_by;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $type;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $has_attachment;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $render_markdown;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\forum\PostHistory", mappedBy="post")
     * @var Collection<PostHistory>
     */
    protected $history;
}
