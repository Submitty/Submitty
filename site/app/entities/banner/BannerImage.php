<?php

declare(strict_types=1);

namespace app\entities\banner;

use app\repositories\banner\BannerImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class BannerImage
 * @package app\entities
 */
#[ORM\Entity(repositoryClass: BannerImageRepository::class)]
#[ORM\Table(name: "community_events")]
class BannerImage {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, precision: 6)]
    protected \DateTime $release_date;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, precision: 6)]
    protected \DateTime $closing_date;

    #[ORM\Column(type: Types::STRING)]
    protected string $name;

    #[ORM\Column(type: Types::STRING)]
    protected string $folder_name;

    #[ORM\Column(type: Types::STRING)]
    protected string $community_path;

    #[ORM\Column(type: Types::STRING)]
    protected string $extra_info;

    #[ORM\Column(type: Types::STRING)]
    protected string $link_name;



    public function __construct(
        string $path,
        string $name,
        string $extra_info_name,
        string $link_url_name,
        \DateTime $release_date,
        \DateTime $close_date,
        string $folder_name
    ) {
        $this->setReleaseDate($release_date);
        $this->setClosingDate($close_date);
        $this->setName($name);
        $this->setExtraInfo($extra_info_name);
        $this->setLinkName($link_url_name);
        $this->setPath($path);
        $this->setFolderName($folder_name);
    }


    public function getId(): int {
        return $this->id;
    }

    public function setPath(string $path): void {
        $this->community_path = $path;
    }

    public function getPath(): string {
        return $this->community_path;
    }
    public function setFolderName(string $folder_name): void {
        $this->folder_name = $folder_name;
    }

    public function getFolderName(): string {
        return $this->folder_name;
    }
    public function setReleaseDate(\DateTime $release_date): void {
        // Convert the DateTime object to a string in the correct format
        $this->release_date = $release_date;
    }

    public function setClosingDate(\DateTime $closing_date): void {
        // Convert the DateTime object to a string in the correct format
        $this->closing_date = $closing_date;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getReleaseDate(): \DateTime {
        return $this->release_date;
    }

    public function getClosingDate(): \DateTime {
        return $this->closing_date;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setExtraInfo(string $name): void {
        $this->extra_info = $name;
    }
    public function getExtraInfo(): string {
        return $this->extra_info;
    }
    public function setLinkName(string $name): void {
        $this->link_name = $name;
    }
    public function getLinkName(): string {
        return $this->link_name;
    }
}
