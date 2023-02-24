<?php

namespace app\entities\forum;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="student_favorites")
 */
class StudentFavorite {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="\app\entities\forum\Thread")
     * @ORM\JoinColumn(name="thread_id", referencedColumnName="id")
     * @var Thread
     */
    protected $thread;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $user_id;
}
