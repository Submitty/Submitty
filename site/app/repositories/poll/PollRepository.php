<?php

declare(strict_types=1);

namespace app\repositories\poll;

use app\entities\poll\Poll;
use Doctrine\ORM\EntityRepository;

class PollRepository extends EntityRepository {
    /**
     * Find all of the polls available to the specified student
     * @return Poll[]
     */
    public function findByStudentID(string $user_id): array {
        return $this->_em
            ->createQuery('
                SELECT p, r FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r WITH r.student_id = :user_id
                WHERE p.release_date <= :release_date
                ORDER BY p.release_date DESC, p.name ASC')
            ->setParameter('release_date', date('Y-m-d'))
            ->setParameter('user_id', $user_id)
            ->getResult();
    }

    /**
     * Find all polls, with all responses hydrated. This function should only be used if all data is strictly necessary.
     * @return Poll[]
     */
    public function findAllWithAllResponses(): array {
        return $this->_em
            ->createQuery('
                SELECT p, r FROM app\entities\poll\Poll p
                LEFT JOIN p.responses r')
            ->getResult();
    }
}
