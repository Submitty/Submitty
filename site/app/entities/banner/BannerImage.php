<?php

declare(strict_types=1);

namespace app\entities\banner;

use app\libraries\DateUtils;
use app\models\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class BannerImage
 * @package app\entities
 * @ORM\Entity(repositoryClass="\app\repositories\banner\BannerImageRepository")
 * @ORM\Table(name="banner_images")
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
     * @ORM\Column(type="datetimetz")
     * @var \DateTime
     */
    protected $release_date;

    /**
     * @ORM\Column(type="datetimetz")
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
    protected $extra_info;


    public function __construct() {
        $this->setReleaseDate(DateUtils::getDateTimeNow());
        $this->setClosingDate(DateUtils::getDateTimeNow());
        $this->setName("hi");
        $this->setExtraInfo("howdy");
    }


    public function setReleaseDate(\DateTime $release_date): void {
        $this->release_date = $release_date;
    }

    public function setClosingDate(\DateTime $closing_date): void {
        $this->closing_date = $closing_date;
    }
    public function setName(string $name): void {
        $this->name = $name;
    }
    public function setExtraInfo(string $name): void {
        $this->extra_info = $name;
    }
}