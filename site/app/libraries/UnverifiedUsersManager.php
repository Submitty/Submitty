<?php

namespace app\libraries;

use app\entities\UnverifiedUserEntity;

class UnverifiedUsersManager {
    /**
     * @return UnverifiedUserEntity[]
     */
    public static function getUnverifiedUsers(Core $core, string $email, string $user_id): array {
        $em = $core->getSubmittyEntityManager();
        $repo = $em->getRepository(UnverifiedUserEntity::class);
        return $repo->getUnverifiedUsers($user_id, $email);
    }

    public static function getUnverifiedUser(Core $core, string $email): ?UnverifiedUserEntity {
        $entity_manager = $core->getSubmittyEntityManager();
        return $entity_manager->getRepository(UnverifiedUserEntity::class)->findOneBy(['user_email' => $email]);
    }

    public static function updateUserVerificationValues(Core $core, string $email, string $verification_code, \DateTime $expiration): bool {
        $entity_manager = $core->getSubmittyEntityManager();
        $unverified_user = UnverifiedUsersManager::getUnverifiedUser($core, $email);

        if ($unverified_user === null) {
            return false;
        }

        $unverified_user->setVerificationCode($verification_code);
        $unverified_user->setVerificationExpiration($expiration);

        $entity_manager->flush();
        return true;
    }
}
