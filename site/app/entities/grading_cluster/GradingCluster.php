<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "grading_cluster")]
class GradingCluster {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(name: "g_id", type: Types::STRING)]
    private string $gradeable_id;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $cluster_name;

    #[ORM\Column(type: Types::STRING, enumType: GradingClusterAlgorithm::class)]
    private GradingClusterAlgorithm $algorithm;

    /**
     * @var Collection<int, GradingClusterMember>
     */
    #[ORM\OneToMany(mappedBy: "cluster", targetEntity: GradingClusterMember::class, cascade: ["persist", "remove"], fetch: "EAGER")]
    private Collection $members;

    public function __construct(string $gradeable_id, ?string $cluster_name, GradingClusterAlgorithm $algorithm) {
        $this->gradeable_id = $gradeable_id;
        $this->cluster_name = $cluster_name;
        $this->algorithm    = $algorithm;
        $this->members      = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getGradeableId(): string {
        return $this->gradeable_id;
    }

    public function getClusterName(): ?string {
        return $this->cluster_name;
    }

    public function setClusterName(?string $cluster_name): void {
        $this->cluster_name = $cluster_name;
    }

    public function getAlgorithm(): GradingClusterAlgorithm {
        return $this->algorithm;
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
