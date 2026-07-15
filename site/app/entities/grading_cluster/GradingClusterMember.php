<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "ta_grading_clusters_members")]
class GradingClusterMember {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: GradingCluster::class, inversedBy: "members")]
    #[ORM\JoinColumn(name: "cluster_id", referencedColumnName: "id", nullable: false)]
    private GradingCluster $cluster;

    #[ORM\Column(name: "user_id", type: Types::STRING, nullable: true)]
    private ?string $user_id;

    #[ORM\Column(name: "team_id", type: Types::STRING, nullable: true)]
    private ?string $team_id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $active_version;

    public function __construct(GradingCluster $cluster, ?string $user_id, ?string $team_id, int $active_version) {
        $this->cluster  = $cluster;
        $this->user_id  = $user_id;
        $this->team_id  = $team_id;
        $this->active_version = $active_version;
        $cluster->addMember($this);
    }

    public function getId(): int {
        return $this->id;
    }

    public function getCluster(): GradingCluster {
        return $this->cluster;
    }

    public function getUserId(): ?string {
        return $this->user_id;
    }

    public function getTeamId(): ?string {
        return $this->team_id;
    }

    public function getActiveVersion(): int {
        return $this->active_version;
    }
}
