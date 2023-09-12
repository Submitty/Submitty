<?php

declare(strict_types=1);

namespace app\entities\banner;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class BannerImage
 * @package app\entities
 * @ORM\Entity(repositoryClass="\app\repositories\banner\BannerImageRepository")
 * @ORM\Table(name="community_events")
 */
class BannerImage {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var integer
     */
    protected $id;
    /**
     * @ORM\Column(type="datetime", precision=6)
     * @var \DateTime
     */
    protected $release_date;

    /**
     * @ORM\Column(type="datetime", precision=6)
     * @var \DateTime
     */
    protected $closing_date;
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $folder_name;
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $path_date;
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $extra_info;



    public function __construct(string $path, string $name, string $extra_info_name, \DateTime $release_date, \DateTime $close_date, string $folder_name) {
        $this->setReleaseDate($release_date);
        $this->setClosingDate($close_date);
        $this->setName($name);
        $this->setExtraInfo($extra_info_name);
        $this->setPath($path);
        $this->setFolderName($folder_name);
    }

    public function getId(): int {
        return $this->id;
    }

    public function setPath(string $path): void {
        $this->path_date = $path;
    }

    public function getPath(): string {
        return $this->path_date;
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
}
