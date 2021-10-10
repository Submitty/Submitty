<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use DateTime;

/**
 * Class PlagiarismRunAccess
 * @package app\entities\plagiarism
 * @ORM\Entity
 * @ORM\Table(name="lichen_run_access")
 */
class PlagiarismRunAccess {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer")
     * @ORM\ManyToOne(targetEntity="app\entities\plagiarism\PlagiarismConfig", inversedBy="id")
     * @var int
     */
    protected $lichen_run_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $user_id;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $timestamp;

    /**
     * PlagiarismRunAccess constructor.
     */
    public function __construct(string $lichen_run_id, string $user_id) {
        $this->lichen_run_id = $lichen_run_id;
        $this->user_id = $user_id;
        $this->timestamp = new DateTime();
    }

    public function getUserID(): string  {
        return $this->user_id;
    }

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }
}
