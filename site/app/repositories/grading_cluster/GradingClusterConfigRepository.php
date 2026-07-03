<?php

declare(strict_types=1);

namespace app\repositories\grading_cluster;

use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<\app\entities\grading_cluster\GradingClusterConfig>
 */
class GradingClusterConfigRepository extends EntityRepository {
    /**
     * Deletes the config cascades and deletes all associated clusters and members
     */
    public function deleteByGradeableId(string $gradeable_id): void {
        $this->getEntityManager()
            ->createQuery('DELETE FROM app\entities\grading_cluster\GradingClusterConfig c WHERE c.gradeable_id = :gradeable_id')
            ->setParameter('gradeable_id', $gradeable_id)
            ->execute();
    }

    /**
     * Fetches a GradingClusterConfig along with all of its clusters and their members
     * in a single query to prevent N+1 query problems.
     */
    public function findWithClustersAndMembers(string $gradeable_id): ?\app\entities\grading_cluster\GradingClusterConfig {
        return $this->getEntityManager()->createQuery('
            SELECT c, cl, m
            FROM \app\entities\grading_cluster\GradingClusterConfig c
            LEFT JOIN c.clusters cl
            LEFT JOIN cl.members m
            WHERE c.gradeable_id = :gradeable_id
        ')
        ->setParameter('gradeable_id', $gradeable_id)
        ->getOneOrNullResult();
    }

    /**
     * Performs a bulk insert of cluster members to avoid N+1 query limits.
     * @param array<int, array<string, mixed>> $membersData Array of member arrays
     */
    public function bulkInsertMembers(array $membersData): void {
        if (empty($membersData)) {
            return;
        }

        $sql = "INSERT INTO ta_grading_clusters_members (cluster_id, user_id, team_id, active_version) VALUES ";
        $values = [];
        $params = [];

        foreach ($membersData as $member) {
            $values[] = "(?, ?, ?, ?)";
            $params[] = $member['cluster_id'];
            $params[] = $member['user_id'];
            $params[] = $member['team_id'];
            $params[] = $member['active_version'];
        }

        $sql .= implode(", ", $values);
        
        $conn = $this->getEntityManager()->getConnection();
        if (method_exists($conn, 'executeStatement')) {
            $conn->executeStatement($sql, $params);
        } else {
            // Fallback for older Doctrine DBAL versions
            $conn->executeUpdate($sql, $params);
        }
    }
}
