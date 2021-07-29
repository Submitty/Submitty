<?php

namespace app\repositories\email;

use app\entities\email\EmailEntity;
use app\models\EmailStatusModel;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 2000;
    const MAX_SUBJECTS_PER_PAGE = 10;
    
    public function getEmailsByPage(int $page, $semester = null, $course = null): object {
        $this->_em->clear();
        $entity = EmailEntity::class;
        $this->_em->getConnection()->getConfiguration()->setSQLLogger(null);
        $course_specific = ($semester && $course) ? "WHERE e.semester = :semester AND e.course = :course " : "";
        $dql = 'SELECT e FROM app\entities\email\EmailEntity e ' . $course_specific . ' ORDER BY e.created DESC';
        $subjects = $this->getPageSubjects($page, $semester, $course);

        $qb = $this->_em->createQueryBuilder();
        $qb->select('e')
           ->from('app\entities\email\EmailEntity', 'e');
        if ($semester && $course) {
            $qb->where('e.semester = :semester')
               ->andWhere('e.course = :course')
               ->setParameter('semester', $semester)
               ->setParameter('course', $course);
        }
        $qb ->orderBy('e.created', 'DESC')
            ->having($qb->expr()->in('e.subject', ':subjects'))
            ->andHaving($qb->expr()->in('e.created', ':time_created'))
            ->setParameter("subjects", $subjects['subjects'])
            ->setParameter("time_created", $subjects['time_created']);

        $paginator = new Paginator($qb->getQuery());
        $this->_em->clear();
        return $paginator->getIterator();
    }

    public function getPageNum($semester = null, $course = null): int {
        $entity = EmailEntity::class;
        $this->_em->clear();
        if ($semester != null || $course != null) {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e WHERE e.semester = '{$semester}' AND e.course = '{$course}' GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        else {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        $q = $this->_em->createQuery($dql);
        $page = 1;
        $count = 0;
        $subject = 0;
        foreach ($q->toIterable() as $email) {
            $count += $email[1];
            $subject += 1;
            if ($count >= self::PAGE_SIZE || $subject == self::MAX_SUBJECTS_PER_PAGE) {
                $page += 1;
                $count = 0;
                $subject = 0;
            }
        }
        $this->_em->clear();
        return $page;
    }

    private function getPageSubjects($page, $semester = null, $course = null): array {
        $entity = EmailEntity::class;
        $this->_em->clear();
        if ($semester != null || $course != null) {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e WHERE e.semester = '{$semester}' AND e.course = '{$course}' GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        else {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        $q = $this->_em->createQuery($dql);
        $curr_page = 1;
        $current_entry = 0;
        $count = 0;
        $subject_count = 0;
        $subjects = [];
        $time_created = [];
        foreach ($q->toIterable() as $email) {
            if ($curr_page > $page) {
                break;
            }
            elseif ($curr_page == $page) {
                $subjects[] = $email['subject'];
                $time_created[] = $email['created']->format("Y-m-d H:i:s");
            }
            $count += $email[1];
            $current_entry += $email[1];
            $subject_count += 1;
            if ($count >= self::PAGE_SIZE || $subject_count == self::MAX_SUBJECTS_PER_PAGE) {
                $curr_page += 1;
                $count = 0;
                $subject_count = 0;
            }
            $end = $current_entry;
        }
        $this->_em->clear();
        return array("subjects" => $subjects, "time_created" => $time_created);
    }
}
