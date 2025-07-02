<?php

declare(strict_types=1);

namespace app\repositories\poll;

use Doctrine\ORM\EntityRepository;

class OptionRepository extends EntityRepository {
    /**
     * Return a mapping of option id -> # responses for the specified poll
     */
    public function findByPollWithResponseCounts(int $poll_id): ?array {
        $query_results = $this->getEntityManager()
            ->createQuery('
                SELECT o.id AS option_id, COUNT(DISTINCT r.student_id) AS num_responses FROM app\entities\poll\Option o
                LEFT JOIN o.user_responses r
                WHERE o.poll = :poll_id
                GROUP BY o.id')
            ->setParameter('poll_id', $poll_id)
            ->getResult();

        $return_array = [];
        foreach ($query_results as $r) {
            $return_array[$r['option_id']] = $r['num_responses'];
        }

        return $return_array;
    }

    /**
     * Return whether given response is in given poll
     */
    public function existsByPollAndResponse(int $poll_id, string $response): bool {
        $query_results = $this->getEntityManager()
            ->createQuery('
                SELECT o.id
                FROM app\entities\poll\Option o
                WHERE o.poll = :poll_id AND o.response = :response')
            ->setParameter('poll_id', $poll_id)
            ->setParameter('response', $response)
            ->setMaxResults(1)
            ->getResult();

        return count($query_results) > 0;
    }
}
