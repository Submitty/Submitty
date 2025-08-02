<?php

declare(strict_types=1);

namespace app\entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for Terms, not much used but used for other queries.
 * @package app\entities
 */
#[ORM\Entity]
#[ORM\Table(name: "terms")]
class Term {
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $term_id;

    #[ORM\Column(type: Types::STRING)]
    protected string $name;

    // In the database it's a Date, but in Php it's type String
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    protected \DateTime $start_date;

    // In the database it's a Date, but in Php it's type String
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    protected \DateTime $end_date;


    public function __construct(string $term_id, string $name, \DateTime $start_date, \DateTime $end_date) {
        $this->term_id = $term_id;
        $this->name = $name;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function getId(): string {
        return $this->term_id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getStartDate(): \DateTime {
        return $this->start_date;
    }

    public function getEndDate(): \DateTime {
        return $this->end_date;
    }
}
