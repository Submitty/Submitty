<?php

namespace app\repositories;

use app\entities\Term;
use Doctrine\ORM\EntityRepository;

class TermRepository extends EntityRepository {

    public function getStartDate(string $term_id): string {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $result = $qb->select('term.start_date')
            ->from('app\entities\Term', 'term')
            ->where('term.term_id = :term_id')
            ->setParameter('term_id', $term_id)
            ->getQuery()
            ->getResult();
        return array_column($result, 'start_date')[0];
    }

    public function getAllTermNames(): array {
        $results = $this->getEntityManager()->createQueryBuilder()
            ->select('term.name')
            ->from('app\entities\Term', 'term')
            ->orderBy('term.term_id', 'DESC')
            ->getQuery()
            ->getResult();;
        return array_column($results, 'name');
    }

    public function createNewTerm(string $term_id, string $term_name, string $start_date, string $end_date): void {
        $em = $this->getEntityManager();
        $term = new Term(
                $term_id,
                $term_name,
                $start_date,
                $end_date,
            );
        $em->persist($term);
        $em->flush();
    }
}
