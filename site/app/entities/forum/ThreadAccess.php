<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="viewed_responses")
 */
class ThreadAccess {
    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\forum\Thread", inversedBy="viewers")
     * @ORM\JoinColumn(name="thread_id", referencedColumnName="id")
     * @ORM\Id
     * @var Thread
     */
    protected $thread;

    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     * @var string
     */
    protected $user_id;

    /**
     * @ORM\Column(type="string")
     * @var DateTime
     */
    protected $timestamp;
}
