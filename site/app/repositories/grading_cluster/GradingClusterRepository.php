<?php

declare(strict_types=1);

namespace app\repositories\grading_cluster;

use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<\app\entities\grading_cluster\GradingCluster>
 */
class GradingClusterRepository extends EntityRepository {
    /**
     * Fetches the specific GradingCluster that the given submitter (user or team) belongs to
     * for a specific gradeable, bypassing the need to load all clusters.
     *
     * @param string $gradeable_id
     * @param string $submitter_id
     * @return \app\entities\grading_cluster\GradingCluster|null
     */
    public function findClusterBySubmitter(string $gradeable_id, string $submitter_id): ?\app\entities\grading_cluster\GradingCluster {
        if ($gradeable_id === '' || $submitter_id === '') {
            return null;
        }

        return $this->getEntityManager()->createQuery('
            SELECT cluster, cluster_member
            FROM \app\entities\grading_cluster\GradingCluster cluster
            JOIN cluster.config cluster_config
            JOIN cluster.members cluster_member
            WHERE cluster_config.gradeable_id = :gradeable_id
              AND EXISTS (
                  SELECT 1
                  FROM \app\entities\grading_cluster\GradingClusterMember search_member
                  WHERE search_member.cluster = cluster 
                    AND (search_member.user_id = :submitter_id OR search_member.team_id = :submitter_id)
              )
        ')
        ->setParameter('gradeable_id', $gradeable_id)
        ->setParameter('submitter_id', $submitter_id)
        ->getOneOrNullResult();
    }
}
