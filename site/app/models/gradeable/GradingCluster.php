<?php

namespace app\models\gradeable;

use app\models\AbstractModel;

/**
 * Represents a single grading cluster for a gradeable.
 * A cluster is a group of students/teams whose submissions
 * have been grouped together (e.g., by an algorithm or AI).
 */
class GradingCluster extends AbstractModel {
    /** @var int Unique ID for this cluster */
    protected int $id;

    /** @var string The gradeable this cluster belongs to */
    protected string $gradeable_id;

    /** @var string|null Optional label given to the cluster (e.g., "Used recursion") */
    protected ?string $label;

    /** @var string The algorithm used to generate this cluster */
    protected string $algorithm;

    /** @var string Timestamp of when the cluster was created */
    protected string $created_at;

    /**
     * List of members in this cluster.
     * Each element is an associative array with keys:
     *   - 'id'        => (int)    grading_cluster_members row id
     *   - 'user_id'   => (string|null) the student user_id (or null for teams)
     *   - 'team_id'   => (string|null) the team_id (or null for individuals)
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $members = [];

    /**
     * @param \app\libraries\Core $core
     * @param array<string, mixed> $details  A row from the grading_cluster table
     */
    public function __construct(\app\libraries\Core $core, array $details) {
        parent::__construct($core);

        $this->id          = (int) $details['id'];
        $this->gradeable_id = $details['g_id'];
        $this->label       = $details['label'] ?? null;
        $this->algorithm   = $details['algorithm'];
        $this->created_at  = $details['created_at'];
    }

    // Getters

    public function getId(): int {
        return $this->id;
    }

    public function getGradeableId(): string {
        return $this->gradeable_id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function getAlgorithm(): string {
        return $this->algorithm;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }

    /** @return array<int, array<string, mixed>> */
    public function getMembers(): array {
        return $this->members;
    }

    public function getMemberCount(): int {
        return count($this->members);
    }

    // Setters / Helpers

    /** @param array<int, array<string, mixed>> $members */
    public function setMembers(array $members): void {
        $this->members = $members;
    }

    public function setLabel(?string $label): void {
        $this->label = $label;
    }
}
