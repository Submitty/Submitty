<?php

namespace app\controllers;

use app\libraries\Core;
use app\entities\UnverifiedUserEntity;

class UnverifiedUserController {
    /**
     * @return UnverifiedUserEntity[]
     */
    public static function getUnverifiedUsers(Core $core, string $email, string $user_id): array {
        return $core->getSubmittyEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from('app\entities\UnverifiedUserEntity', 'u')
            ->where('u.user_email = :email')
            ->orWhere('u.user_id = :user_id')
            ->setParameter('email', $email)
            ->setParameter('user_id', $user_id)
            ->getQuery()
            ->getResult();
    }

    public static function getUnverifiedUser(Core $core, string $email): ?UnverifiedUserEntity {
        $entity_manager = $core->getSubmittyEntityManager();
        return $entity_manager->getRepository(UnverifiedUserEntity::class)->findOneBy(['user_email' => $email]);
    }

    public static function updateUserVerificationValues(Core $core, string $email, string $verification_code, \DateTime $expiration): bool {
        $entity_manager = $core->getSubmittyEntityManager();
        $unverified_user = UnverifiedUserController::getUnverifiedUser($core, $email);

        if ($unverified_user === null) {
            return false;
        }

        $unverified_user->setVerificationCode($verification_code);
        $unverified_user->setVerificationExpiration($expiration);

        $entity_manager->flush();
        return true;
    }
}
