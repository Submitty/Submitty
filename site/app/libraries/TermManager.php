<?php

namespace app\libraries;

use app\entities\Term;  
use app\repositories\TermRepository;
use Doctrine\ORM\EntityManager;
use app\models\User;

/**
 * Class TermManager
 */
class TermManager {
    private Core $core;
    private EntityManager $em;
    private TermRepository $repo;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->em = $this->core->getSubmittyEntityManager();
        /** @var TermRepository<Term> $repo */
        $this->repo = $this->em->getRepository(Term::class);
    }

    public function getTermStartDate(string $term_id, User $user): string {
        $timestamp = $this->repo->getTermStartDate($term_id);
        return DateUtils::convertTimeStamp($user, $timestamp, 'Y-m-d H:i:s');
    }

    /**
     * @return string[]
     */
    public function getAllTermNames(): array {
        return $this->repo->getAllTermNames();
    }

    public function createNewTerm(string $term_id, string $term_name, string $start_date, string $end_date): void {
        $term = new Term(
            $term_id,
            $term_name,
            $start_date,
            $end_date,
        );
        $this->em->persist($term);
        $this->em->flush();
    }
}
