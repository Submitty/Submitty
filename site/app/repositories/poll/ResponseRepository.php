<?php

declare(strict_types=1);

namespace app\repositories\poll;

use Doctrine\ORM\EntityRepository;

class ResponseRepository extends EntityRepository {
    /**
     * @return array[int]
     */
    public function numResponsesByPoll(): array {
        $response_counts = $this->_em
            ->createQueryBuilder()
            ->select('(r.poll) AS poll_id', '(COUNT(DISTINCT r.student_id)) AS num_responses')
            ->from('app\entities\poll\Response', 'r')
            ->groupBy('r.poll')
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($response_counts as $r) {
            $results[$r['poll_id']] = $r['num_responses'];
        }
        return $results;
    }
}
