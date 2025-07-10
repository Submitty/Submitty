<?php

namespace app\repositories;

use Doctrine\ORM\EntityRepository;
use app\entities\Term;

/**
 * Repository for Term entities.
 * Provides methods to retrieve term information from the database.
 * @template T of Term
 * @extends EntityRepository<T>
 */
class TermRepository extends EntityRepository {
    public function getTermStartDate(string $term_id): string {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $result = $qb->select('term.start_date')
            ->from('app\entities\Term', 'term')
            ->where('term.term_id = :term_id')
            ->setParameter('term_id', $term_id)
            ->getQuery()
            ->getResult();
        return array_column($result, 'start_date')[0];
    }

    /**
     * @return string[]
     */
    public function getAllTermNames(): array {
        $results = $this->getEntityManager()->createQueryBuilder()
            ->select('term.name')
            ->from('app\entities\Term', 'term')
            ->orderBy('term.term_id', 'DESC')
            ->getQuery()
            ->getResult();
        return array_column($results, 'name');
    }
}
