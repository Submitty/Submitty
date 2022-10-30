<?php

declare(strict_types=1);

namespace app\repositories\poll;

use Doctrine\ORM\EntityRepository;

class ResponseRepository extends EntityRepository
{
    /**
     * @return \app\entities\poll\Response[]
    */
    public function findByPollIdAndStudentId(int $poll_id, string $student_id): array {
        return $this->_em
            ->createQuery(
                'SELECT r FROM app\entities\poll\Response r WHERE r.poll_id = :poll_id AND r.student_id = :student_id'
            )
            ->setParameter('poll_id', $poll_id)
            ->setParameter('student_id', $student_id)
            ->getResult();
    }

    /**
     * @return array<int, \app\entities\poll\Response[]>
     */
    public function findAllResponsesByStudentId(string $student_id): array {
        /** @var $responses \app\entities\poll\Response[] */
        $responses = $this->_em
            ->createQuery(
                'SELECT r FROM app\entities\poll\Response r WHERE r.student_id = :student_id'
            )
            ->setParameter('student_id', $student_id)
            ->getResult();

        $results = [];
        foreach ($responses as $r) {
            if (!array_key_exists($r->getPoll()->getId(), $results)) {
                $results[$r->getPoll()->getId()] = [];
            }
            $results[$r->getPoll()->getId()][] = $r;
        }
        return $results;
    }

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
