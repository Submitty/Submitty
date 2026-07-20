<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "ta_grading_clusters")]
class GradingCluster {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: GradingClusterConfig::class, inversedBy: "clusters")]
    #[ORM\JoinColumn(name: "config_id", referencedColumnName: "id", nullable: false)]
    private GradingClusterConfig $config;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $cluster_name;

    /**
     * @var Collection<int, GradingClusterMember>
     */
    #[ORM\OneToMany(mappedBy: "cluster", targetEntity: GradingClusterMember::class, cascade: ["persist", "remove"], fetch: "EAGER")]
    private Collection $members;

    public function __construct(GradingClusterConfig $config, ?string $cluster_name) {
        $this->config       = $config;
        $this->cluster_name = $cluster_name;
        $this->members      = new ArrayCollection();
        $config->addCluster($this);
    }

    public function getId(): int {
        return $this->id;
    }

    public function getConfig(): GradingClusterConfig {
        return $this->config;
    }

    public function getClusterName(): ?string {
        return $this->cluster_name;
    }

    public function setClusterName(?string $cluster_name): void {
        $this->cluster_name = $cluster_name;
    }

    /**
     * @return Collection<int, GradingClusterMember>
     */
    public function getMembers(): Collection {
        return $this->members;
    }

    public function getMemberCount(): int {
        return $this->members->count();
    }

    public function addMember(GradingClusterMember $member): void {
        $this->members->add($member);
    }
}
