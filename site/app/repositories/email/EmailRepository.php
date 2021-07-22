<?php


namespace app\repositories\email;

use app\entities\db\EmailEntity;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

class EmailRepository extends EntityRepository {
    const PAGE_SIZE = 1000;

    public function getEmailsByPage(int $page): array {

        $dql = "";
        $query = $this->_em->createQuery($dql)
            ->setFirstResult(($page-1)*self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);
            //->getResult(); maybe

        $paginator = new Paginator($query);
        
        foreach ($paginator as $email) {
            /** @var EmailEntity $email */

        }

    }
}
