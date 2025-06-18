<?php

namespace app\libraries;

use app\entities\Term;
use app\repositories\TermRepository;
use app\models\User;
/**
 * Class TermManager
 *
 */
class TermManager {
    public static function getTermStartDate(Core $core, string $term_id, User $user): string {
        $em = $core->getSubmittyEntityManager();
        /** @var TermRepository $repo */
        $repo = $em->getRepository(Term::class);
        $timestamp = $repo->getStartDate($term_id);
        return DateUtils::convertTimeStamp($user, $timestamp, 'Y-m-d H:i:s');
    }

    /**
     * @return array<string>
     */
    public static function getAllTermNames(Core $core): array {
        $em = $core->getSubmittyEntityManager();
        /** @var TermRepository $repo */
        $repo = $em->getRepository(Term::class);
        return $repo->getAllTermNames();
    }

    public static function createNewTerm(Core $core, string $term_id, string $name, string $start_day, string $end_day): void {
        $em = $core->getSubmittyEntityManager();
        /** @var TermRepository $repo */
        $repo = $em->getRepository(Term::class);
        $repo->createNewTerm($term_id, $name, $start_day, $end_day);
    }
}
