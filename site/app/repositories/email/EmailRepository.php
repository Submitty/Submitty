<?php

namespace app\repositories\email;

use Doctrine\ORM\EntityRepository;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 5000;
    const MAX_SUBJECTS_PER_PAGE = 10;

    /**
     * Fetches a specific page of email entities, grouped by subject and created date, ordered from newest to oldest.
     *
     * @param int $page
     * @param string|null $semester
     * @param string|null $course
     * @return array<int, iterable<int, mixed>>
     */
    public function getEmailsByPage(int $page, ?string $semester, ?string $course): array {
        $subjects = $this->getPageSubjects($page, $semester, $course);
        $result = [];

        foreach ($subjects as $subject) {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $qb ->select('e')
                ->from('app\entities\email\EmailEntity', 'e');
            if ($semester !== null && $course !== null) {
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

    /**
     * Helper method to fetch all ordered emails for pagination, which contain their subject,
     * created date, and count.
     *
     * @param string|null $semester
     * @param string|null $course
     * @return iterable<array<string, mixed>>
     */
    private function fetchEmails(?string $semester, ?string $course): iterable {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb ->select('e.subject, e.created, COUNT(e) AS cnt')
            ->from('app\entities\email\EmailEntity', 'e');
        if ($semester !== null && $course !== null) {
            $qb->where('e.term = :term')
                ->andWhere('e.course = :course')
                ->setParameter('term', $semester)
                ->setParameter('course', $course);
        }
        $qb->groupBy('e.subject, e.created')
            ->orderBy('e.created', 'DESC');
        return $qb->getQuery()->toIterable();
    }

    /**
     * Gets the total number of email pages for pagination.
     *
     * @param string|null $semester
     * @param string|null $course
     * @return int
     */
    public function getPageNum(?string $semester, ?string $course): int {
        // Add 1 as no placeholder element is added for the first page
        return count($this->getPageSubjects(PHP_INT_MAX, $semester, $course)) + 1;
    }

    /**
     * Helper method to fetch the subjects for a specific page for pagination, containing their subject, created date,
     * and count of emails.
     *
     * @param int $page
     * @param string|null $semester
     * @param string|null $course
     * @return array<int, array<string, mixed>>
     */
    private function getPageSubjects(int $page, ?string $semester, ?string $course): array {
        $emails = $this->fetchEmails($semester, $course);
        $curr_page = 1;
        $count = 0;
        $subject_count = 0;
        $subjects = [];
        foreach ($emails as $email) {
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
                elseif ($page === PHP_INT_MAX) {
                    // Each placeholder element represents a page to simplify page calculation
                    array_push($subjects, null);
                }
            }
        }

        return $subjects;
    }
}
