<?php

namespace app\repositories\email;

use Doctrine\ORM\EntityRepository;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 5000;
    const MAX_SUBJECTS_PER_PAGE = 10;

    public function getEmailsByPage(int $page, $semester = null, $course = null): array {
        $subjects = $this->getPageSubjects($page, $semester, $course);
        $result = [];

        foreach ($subjects as $subject) {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $qb ->select('e')
                ->from('app\entities\email\EmailEntity', 'e');
            if ($semester && $course) {
                $qb->where('e.term = :term')
                    ->andWhere('e.course = :course')
                    ->setParameter('term', $semester)
                    ->setParameter('course', $course);
            }
            $qb ->orderBy('e.created', 'DESC')
                ->andWhere('e.subject = :subject')
                ->andWhere('e.created = :created')
                ->setParameter("subject", $subject['subject'])
                ->setParameter("created", $subject['created']);
            $result[] = $qb->getQuery()->toIterable();
        }

        return $result;
    }

    public function getPageNum($semester = null, $course = null): int {
        if ($semester != null || $course != null) {
            $dql = 'SELECT e.subject, e.created, COUNT(e) AS cnt FROM app\entities\email\EmailEntity e WHERE e.term = \'' . $semester . '\' AND e.course = \'' . $course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        else {
            $dql = 'SELECT e.subject, e.created, COUNT(e) AS cnt FROM app\entities\email\EmailEntity e GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        $q = $this->getEntityManager()->createQuery($dql);
        $page = 1;
        $count = 0;
        $subject = 0;
        foreach ($q->toIterable() as $email) {
            $count += $email['cnt'];
            $subject += 1;
            if ($count >= self::PAGE_SIZE || $subject > self::MAX_SUBJECTS_PER_PAGE) {
                $page += 1;
                $count = 0;
                $subject = 1;
            }
        }
        return $page;
    }

    private function getPageSubjects($page, $semester = null, $course = null): array {
        if ($semester != null || $course != null) {
            $dql = 'SELECT e.subject, e.created, COUNT(e) AS cnt FROM app\entities\email\EmailEntity e WHERE e.term = \'' . $semester . '\' AND e.course = \'' . $course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        else {
            $dql = 'SELECT e.subject, e.created, COUNT(e) AS cnt FROM app\entities\email\EmailEntity e GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        $q = $this->getEntityManager()->createQuery($dql);
        $curr_page = 1;
        $count = 0;
        $subject_count = 0;
        $subjects = [];
        foreach ($q->toIterable() as $email) {
            $count += $email['cnt'];
            $subject_count += 1;
            if ($curr_page === $page) {
                array_push($subjects, [
                    "subject" => $email['subject'],
                    "created" => $email['created']->format("Y-m-d H:i:s.u"),
                    "cnt" => $email['cnt']
                ]);
            }
            if ($count >= self::PAGE_SIZE || $subject_count === self::MAX_SUBJECTS_PER_PAGE) {
                $curr_page += 1;
                $count = 0;
                $subject_count = 0;

                if ($curr_page > $page) {
                    break;
                }
            }
        }
        return $subjects;
    }
}
