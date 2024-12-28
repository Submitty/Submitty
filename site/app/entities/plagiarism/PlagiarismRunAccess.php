<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use app\libraries\DateUtils;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * Class PlagiarismRunAccess
 * @package app\entities\plagiarism
 */
#[ORM\Entity]
#[ORM\Table(name: "lichen_run_access")]
class PlagiarismRunAccess {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: PlagiarismConfig::class, inversedBy: "access_times")]
    protected PlagiarismConfig $lichen_run;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $user_id;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $timestamp;

    /**
     * PlagiarismRunAccess constructor.
     */
    public function __construct(PlagiarismConfig $lichen_run_id, string $user_id) {
        $this->lichen_run = $lichen_run_id;
        $this->user_id = $user_id;
        $this->timestamp = DateUtils::getDateTimeNow();
    }



    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }
}
