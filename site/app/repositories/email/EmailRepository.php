<?php

namespace app\repositories\email;

use Doctrine\ORM\EntityRepository;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 5000;
    const MAX_SUBJECTS_PER_PAGE = 10;

    public function getEmailsByPage(int $page, $term = null, $course = null): array {
        $this->_em->getConnection()->getConfiguration()->setSQLLogger(null);
        $subjects = $this->getPageSubjects($page, $term, $course);
        $result = [];

        foreach ($subjects as $subject) {
            $qb = $this->_em->createQueryBuilder();
            $qb ->select('e')
                ->from('app\entities\email\EmailEntity', 'e');
            if ($term && $course) {
                $qb->where('e.term = :term')
                    ->andWhere('e.course = :course')
                    ->setParameter('term', $term)
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

    public function getPageNum($term = null, $course = null): int {
        if ($term != null || $course != null) {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e WHERE e.term = \'' . $term . '\' AND e.course = \'' . $course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        else {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        $q = $this->_em->createQuery($dql);
        $page = 1;
        $count = 0;
        $subject = 0;
        $new_page_flag = false;
        foreach ($q->toIterable() as $email) {
            $count += $email[1];
            $subject += 1;
            if ($count >= self::PAGE_SIZE || $subject > self::MAX_SUBJECTS_PER_PAGE) {
                $page += 1;
                $count = 0;
                $subject = 1;
            }
        }
        return $page;
    }

    private function getPageSubjects($page, $term = null, $course = null): array {
        if ($term != null || $course != null) {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e WHERE e.term = \'' . $term . '\' AND e.course = \'' . $course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        else {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        $q = $this->_em->createQuery($dql);
        $curr_page = 1;
        $count = 0;
        $subject_count = 0;
        $subjects = [];
        foreach ($q->toIterable() as $email) {
            $count += $email[1];
            $subject_count += 1;
            if ($curr_page > $page) {
                break;
            }
            elseif ($curr_page == $page) {
                $subjects[] = ["subject" => $email['subject'], "created" => $email['created']->format("Y-m-d H:i:s.u")];
            }
            if ($count >= self::PAGE_SIZE || $subject_count == self::MAX_SUBJECTS_PER_PAGE) {
                $curr_page += 1;
                $count = 0;
                $subject_count = 0;
            }
        }
        return $subjects;
    }
}
