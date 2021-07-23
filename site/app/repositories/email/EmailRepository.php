<?php


namespace app\repositories\email;

use app\entities\email\EmailEntity;
use app\models\EmailStatusModel;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 1000;
    const MAX_SUBJECTS_PER_PAGE = 10;

    public function getEmailsByPage(int $page, $semester = null, $course = null): Paginator {
        $entity = EmailEntity::class;
        $course_specific = ($semester && $course) ? "WHERE e.semester = '{$semester}' AND e.course = '{$course}' " : "";
        $dql = "SELECT e FROM {$entity} e {$course_specific} ORDER BY e.created DESC";
        $window = $this->getPageWindow($page, $semester, $course);
        $query = $this->_em->createQuery($dql)
            ->setFirstResult($window[0])
            ->setMaxResults($window[1]);
            //->getResult(); maybe

        $paginator = new Paginator($query);
        
        return $paginator;
    }

    public function getPageNum($semester = null, $course = null): int {
        $entity = EmailEntity::class;
        if ($semester != null || $course != null) {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e WHERE e.semester = '{$semester}' AND e.course = '{$course}' GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        else {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        $q = $this->_em->createQuery($dql);
        $query_res = $q->getResult();
        $page = 1;
        $count = 0;
        $subject = 0;
        foreach ($query_res as $email) {
            $count += $email[1];
            $subject += 1;
            if ($count > self::PAGE_SIZE || $subject >= self::MAX_SUBJECTS_PER_PAGE) {
                $page += 1;
                $count = 0;
                $subject = 0;
            }
        }
        return $page;
    }

    private function getPageWindow($page, $semester = null, $course = null): array {
        $entity = EmailEntity::class;
        if ($semester != null || $course != null) {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e WHERE e.semester = '{$semester}' AND e.course = '{$course}' GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        else {
            $dql = "SELECT e.subject, e.created, COUNT(e) FROM {$entity} e GROUP BY e.subject, e.created ORDER BY e.created DESC";
        }
        $q = $this->_em->createQuery($dql);
        $query_res = $q->getResult();
        $curr_page = 1;
        $current_entry = 0;
        $count = 0;
        $subject = 0;
        foreach ($query_res as $email) {
            if ($curr_page > $page) {
                break;
            }
            else if ($curr_page == $page) {
                if (!isset($start)) {
                    $start = $current_entry;
                }
            }
            $count += $email[1];
            $current_entry += $email[1];
            $subject += 1;
            if ($count > self::PAGE_SIZE || $subject >= self::MAX_SUBJECTS_PER_PAGE) {
                $curr_page += 1;
                $count = 0;
                $subject = 0;
            }
            $end = $current_entry;
        }
        return [$start, $end-$start];
    }
}
