<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "grading_cluster_config")]
class GradingClusterConfig {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private int $id;

    #[ORM\Column(name: "g_id", type: Types::STRING, unique: true)]
    private string $gradeable_id;

    #[ORM\Column(type: Types::STRING, enumType: GradingClusterAlgorithm::class)]
    private GradingClusterAlgorithm $algorithm;

    /**
     * @var Collection<int, GradingCluster>
     */
    #[ORM\OneToMany(mappedBy: "config", targetEntity: GradingCluster::class, cascade: ["persist", "remove"], fetch: "EAGER")]
    private Collection $clusters;

    public function __construct(string $gradeable_id, GradingClusterAlgorithm $algorithm) {
        $this->gradeable_id = $gradeable_id;
        $this->algorithm    = $algorithm;
        $this->clusters     = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getGradeableId(): string {
        return $this->gradeable_id;
    }

    public function getAlgorithm(): GradingClusterAlgorithm {
        return $this->algorithm;
    }

    /**
     * @return Collection<int, GradingCluster>
     */
    public function getClusters(): Collection {
        return $this->clusters;
    }

    public function addCluster(GradingCluster $cluster): void {
        $this->clusters->add($cluster);
    }
}
