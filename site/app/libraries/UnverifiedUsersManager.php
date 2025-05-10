<?php

namespace app\libraries;

use app\entities\UnverifiedUserEntity;

class UnverifiedUsersManager {
    /**
     * @return UnverifiedUser[]
     */
    public static function getUnverifiedUsers(Core $core, string $email, string $user_id): array {
        $em = $core->getSubmittyEntityManager();
        $repo = $em->getRepository(UnverifiedUserEntity::class);
        return $repo->getUnverifiedUsers($user_id, $email);
    }
}
