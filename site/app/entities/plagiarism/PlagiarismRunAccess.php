<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use app\libraries\DateUtils;
use Doctrine\ORM\Mapping as ORM;
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
     * @ORM\ManyToOne(targetEntity="app\entities\plagiarism\PlagiarismConfig", inversedBy="access_times")
     * @var PlagiarismConfig
     */
    protected $lichen_run;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $user_id;

    /**
     * @ORM\Column(type="datetimetz")
     * @var DateTime
     */
    protected $timestamp;

    /**
     * PlagiarismRunAccess constructor.
     */
    public function __construct(PlagiarismConfig $lichen_run_id, string $user_id) {
        $this->lichen_run = $lichen_run_id;
        $this->user_id = $user_id;
        $this->timestamp = DateUtils::getDateTimeNow();
    }

    public function getUserID(): string {
        return $this->user_id;
    }

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }
}
