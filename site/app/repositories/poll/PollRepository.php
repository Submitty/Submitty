<?php

declare(strict_types=1);

namespace app\repositories\poll;

use app\entities\poll\Poll;
use Doctrine\ORM\EntityRepository;

class PollRepository extends EntityRepository {
    /**
     * Find all of the polls available to the specified student, and the  and hydrate all options
     * @return Poll[]
     */
    public function findAllByStudentIDWithAllOptions(string $user_id): array {
        return $this->getEntityManager()
            ->createQuery('
                SELECT p, r, o FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r WITH r.student_id = :user_id
                LEFT JOIN p.options o
                WHERE p.release_date <= :release_date
                ORDER BY p.release_date ASC, p.name ASC')
            ->setParameter('release_date', date('Y-m-d'))
            ->setParameter('user_id', $user_id)
            ->getResult();
    }

    /**
     * Find single poll and hydrate all options and the specific responses for the specified student
     */
    public function findByStudentID(string $user_id, int $poll_id): ?Poll {
        $result = $this->getEntityManager()
            ->createQuery('
                SELECT p, r, o FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r WITH r.student_id = :user_id
                LEFT JOIN p.options o
                WHERE p.id = :poll_id AND p.release_date <= :release_date')
            ->setParameter('release_date', date('Y-m-d'))
            ->setParameter('poll_id', $poll_id)
            ->setParameter('user_id', $user_id)
            ->getResult();
        if (count($result) === 0) {
            return null;
        }
        return $result[0];
    }

    /**
     * Find a single poll specified by ID and hydrate options
     */
    public function findByIDWithOptions(int $poll_id): ?Poll {
        $result = $this->getEntityManager()
            ->createQuery('
                SELECT p, o FROM app\entities\poll\Poll p
                LEFT JOIN p.options o
                WHERE p.id = :poll_id')
            ->setParameter('poll_id', $poll_id)
            ->getResult();
        if (count($result) === 0) {
            return null;
        }
        return $result[0];
    }

    /**
     * Find all polls, with all responses hydrated. This function should only be used if all data is strictly necessary.
     * @return Poll[]
     */
    public function findAllWithAllResponses(): array {
        return $this->getEntityManager()
            ->createQuery('
                SELECT p, r FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r
                ORDER BY p.release_date ASC, p.name ASC')
            ->getResult();
    }


    /**
     * Get all the polls and the number of responses to each of them
     * @return array<array{poll: Poll, num_responses: int}>
     */
    public function findAllWithNumResponses(): array {
        return $this->getEntityManager()
            ->createQuery('
                SELECT p AS poll, COUNT(DISTINCT r.student_id) AS num_responses
                FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r
                GROUP BY p.id
                ORDER BY p.release_date ASC, p.name ASC')
            ->getResult();
    }
}
