<?php

declare(strict_types=1);

namespace app\entities\calendar;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="calendar_messages")
 */
class CalendarItem {
    // Note the 'Important' type is also treated as regular text
    const TEXT = 0;
    const ANNOUNCEMENT = 1;

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

    public function getId(): int {
        return $this->id;
    }

    public function isAnnouncement(): bool {
        return $this->type === self::ANNOUNCEMENT;
    }

    public function isText(): bool {
        return $this->type === self::TEXT;
    }

    public function getDate(): DateTime {
        return $this->date;
    }

    public function getText(): string {
        return $this->text;
    }

    public function setDate(DateTime $date): void {
        $this->date = $date;
    }

    public function setText(string $text): void {
        if (strlen($text) > 255) {
            throw new \InvalidArgumentException("Invalid text, may be too many characters.");
        }
        $this->text = $text;
    }

    public function setType(int $type): void {
        if ($type === self::ANNOUNCEMENT || $type === self::TEXT) {
            $this->type = $type;
        }
        throw new \InvalidArgumentException("Invalid type specified.");
    }

    public function setStringType(string $type): void {
        switch ($type) {
            case 'note':
                $this->type = self::TEXT;
                break;
            case 'important':
                $this->type = self::TEXT;
                break;
            case 'announcement':
                $this->type = self::ANNOUNCEMENT;
                break;
            default:
                throw new \InvalidArgumentException("Invalid type specified.");
        }
    }

    public function getType(): int {
        return $this->type;
    }

    public function getTypeString(): string {
        switch ($this->type) {
            case self::TEXT:
                return 'text';
            case self::ANNOUNCEMENT:
                return 'ann';
        }
        throw new \RuntimeException("This type is not a text or announcement.");
    }
}
