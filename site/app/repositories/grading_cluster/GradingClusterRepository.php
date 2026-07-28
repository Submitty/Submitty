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
        return $this->getEntityManager()->createQuery('
            SELECT cl, m
            FROM \app\entities\grading_cluster\GradingCluster cl
            JOIN cl.config c
            JOIN cl.members m
            WHERE c.gradeable_id = :gradeable_id
              AND EXISTS (
                  SELECT 1
                  FROM \app\entities\grading_cluster\GradingClusterMember m2
                  WHERE m2.cluster = cl 
                    AND (m2.user_id = :submitter_id OR m2.team_id = :submitter_id)
              )
        ')
        ->setParameter('gradeable_id', $gradeable_id)
        ->setParameter('submitter_id', $submitter_id)
        ->getOneOrNullResult();
    }
}
