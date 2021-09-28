<?php

declare(strict_types=1);

namespace app\entities\forum;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="threads")
 */
class Thread {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $title;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $created_by;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $pinned;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $deleted;

    /**
     * @ORM\ManyToOne(targetEntity="Thread", inversedBy="merged_on_this")
     * @ORM\JoinColumn(name="merged_thread_id", referencedColumnName="id")
     * @var Thread
     */
    protected $merged_thread;

    /**
     * @ORM\OneToMany(targetEntity="Thread", mappedBy="merged_thread")
     * @var Collection<Thread>
     */
    protected $merged_on_this;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\forum\Post", inversedBy="merged_threads")
     * @ORM\JoinColumn(name="merged_post_id", referencedColumnName="id")
     * @var Post
     */
    protected $merged_post;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $is_visible;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $status;

    /**
     * @ORM\Column(type="datetimetzmicro")
     * @var DateTime
     */
    protected $lock_thread_date;

    /**
     * @ORM\Column(type="datetimetzmicro")
     * @var DateTime
     */
    protected $pinned_expiration;

    /**
     * @ORM\Column(type="datetimetzmicro")
     * @var DateTime
     */
    protected $announced;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\forum\Post", mappedBy="thread")
     * @var Collection<Post>
     */
    protected $posts;

    /**
     * @ORM\ManyToMany(targetEntity="\app\entities\forum\Category", inversedBy="threads")
     * @ORM\JoinTable(name="thread_categories",
     *     joinColumns={@ORM\JoinColumn(name="thread_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="category_id", referencedColumnName="category_id")})
     * @var Collection<Category>
     */
    protected $categories;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\forum\ThreadAccess", mappedBy="thread")
     * @var Collection<ThreadAccess>
     */
    protected $viewers;
}
