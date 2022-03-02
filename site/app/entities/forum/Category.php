<?php

namespace app\entities\forum;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="categories_list")
 */
class Category {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $category_id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $category_desc;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    protected $rank;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $color;

    /**
     * @ORM\ManyToMany(targetEntity="\app\entities\forum\Thread", mappedBy="categories")
     * @var Collection<Thread>
     */
    protected $threads;
}
