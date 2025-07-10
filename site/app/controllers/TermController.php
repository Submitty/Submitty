<?php

namespace app\controllers;

use app\entities\Term;
use app\libraries\Core;

/**
 * Class TermController
 */
class TermController extends AbstractController {
    public static function getTermStartDate(Core $core, string $term_id): string {
        return $core->getSubmittyEntityManager()
            ->find(Term::class, $term_id)
            ->getStartDate();
    }

    public static function getAllTermNames(Core $core): array {
         return $core->getSubmittyEntityManager()
            ->createQueryBuilder()
            ->select('term.name')
            ->from(Term::class, 'term')
            ->orderBy('term.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public static function createNewTerm(Core $core, string $term_id, string $term_name, string $start_date, string $end_date): Term {
        $em = $core->getSubmittyEntityManager();
        $term = new Term(
            $term_id,
            $term_name,
            $start_date,
            $end_date,
        );
        $em->persist($term);
        $em->flush();
        return $term;
    }
}
