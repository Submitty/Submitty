<?php

namespace app\repositories\email;

use app\models\EmailStatusModel;
use Doctrine\ORM\EntityRepository;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 5000;
    const MAX_SUBJECTS_PER_PAGE = 10;
    
    public function getEmailsByPage(int $page, $semester = null, $course = null): array {
        $this->_em->getConnection()->getConfiguration()->setSQLLogger(null);
        $subjects = $this->getPageSubjects($page, $semester, $course);
        $result = [];

        foreach ($subjects as $subject) {
            $qb = $this->_em->createQueryBuilder();
            $qb ->select('e')
                ->from('app\entities\email\EmailEntity', 'e');
            if ($semester && $course) {
                $qb->where('e.semester = :semester')
                    ->andWhere('e.course = :course')
                    ->setParameter('semester', $semester)
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
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e WHERE e.semester = \''. $semester .'\' AND e.course = \'' .$course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
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

    private function getPageSubjects($page, $semester = null, $course = null): array {
        if ($semester != null || $course != null) {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e WHERE e.semester = \''. $semester .'\' AND e.course = \'' .$course . '\' GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        else {
            $dql = 'SELECT e.subject, e.created, COUNT(e) FROM app\entities\email\EmailEntity e GROUP BY e.subject, e.created ORDER BY e.created DESC';
        }
        $q = $this->_em->createQuery($dql);
        $curr_page = 1;
        $current_entry = 0;
        $count = 0;
        $subject_count = 0;
        $subjects = [];
        foreach ($q->toIterable() as $email) {
            $count += $email[1];
            $current_entry += $email[1];
            $subject_count += 1;
            if ($curr_page > $page) {
                break;
            }
            elseif ($curr_page == $page) {
                $subjects[] = array("subject" => $email['subject'], "created" => $email['created']->format("Y-m-d H:i:s.u"));
            }
            if ($count >= self::PAGE_SIZE || $subject_count > self::MAX_SUBJECTS_PER_PAGE) {
                $curr_page += 1;
                $count = 0;
                $subject_count = 1;
                if ($curr_page == $page) {
                    $subjects[] = array("subject" => $email['subject'], "created" => $email['created']->format("Y-m-d H:i:s.u"));
                }
            }
            
        }
        return $subjects;
    }
}
