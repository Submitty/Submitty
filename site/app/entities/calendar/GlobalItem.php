<?php

declare(strict_types=1);

namespace app\entities\calendar;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "global_calendar_items")]
class GlobalItem {
    public const TEXT = 0;
    public const ANNOUNCEMENT = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    protected int $id;

    #[ORM\Column(name: "type", type: Types::INTEGER)]
    protected int $type;

    #[ORM\Column(name: "text", type: Types::STRING)]
    protected string $text;

    #[ORM\Column(name: "date", type: Types::DATE_MUTABLE)]
    protected DateTime $date;

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
        if (strlen($text) > 255 || strlen($text) <= 0) {
            throw new \InvalidArgumentException("Invalid text.");
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
            case 'text':
                $this->type = self::TEXT;
                break;
            case 'ann':
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
        throw new \RuntimeException();
    }
}
