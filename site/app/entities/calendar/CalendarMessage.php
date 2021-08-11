<?php

declare(strict_types=1);

namespace app\entities\calendar;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CalendarMessage
 * @package app\entities\calendar
 * @ORM\Entity
 * @ORM\Table(name="calendar_messages")
 */
class CalendarMessage {
    const NOTE = 0;
    const ANNOUNCEMENT = 1;
    const IMPORTANT = 2;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $type;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $text;

    /**
     * @ORM\Column(type="date")
     * @var DateTime
     */
    protected $date;

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    public function isNote(): bool {
        return $this->type === self::NOTE;
    }

    public function isAnnouncement(): bool {
        return $this->type === self::ANNOUNCEMENT;
    }

    public function isImportant(): bool {
        return $this->type === self::IMPORTANT;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getText(): string {
        return $this->text;
    }

    /**
     * @param DateTime $date
     */
    public function setDate(DateTime $date): void {
        $this->date = $date;
    }

    /**
     * @param string $text
     */
    public function setText(string $text): void {
        $this->text = $text;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }
}
