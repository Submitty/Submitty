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
     * Fetches a GradingClusterConfig along with all of its clusters and their members in a single query.
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
}
