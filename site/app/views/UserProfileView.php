<?php

namespace app\views;

use app\libraries\Access;
use app\libraries\DateUtils;
use app\models\User;

/**
 * Class UserProfileView
 * @package app\views
 */
class UserProfileView extends AbstractView {
    /**
     * @param User $user
     * @param string $change_name_text
     * @param bool $database_authentication
     * @param string $csrf_token
     * @return string
     */
    public function showUserProfile(
        User $user,
        string $change_name_text,
        bool $database_authentication,
        string $csrf_token
    ) {
        $autofill_preferred_name = [$user->getLegalGivenName(), $user->getLegalFamilyName()];
        if ($user->getPreferredGivenName() != "") {
            $autofill_preferred_name[0] = $user->getPreferredGivenName();
        }
        if ($user->getPreferredFamilyName() != "") {
            $autofill_preferred_name[1] = $user->getPreferredFamilyName();
        }

        $this->output->addInternalJs('user-profile.js');
        $this->output->addInternalCss('user-profile.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->output->setPageName('My Profile');

        $user_utc_offset = DateUtils::getUTCOffset($user->getTimeZone());
        $user_time_zone_with_offset = $user_utc_offset === 'NOT SET'
            ?  $user->getTimeZone()
            : "(UTC" . $user_utc_offset . ") " . $user->getTimeZone();

        $this->core->getOutput()->addInternalModuleJs('user-profile.js');

        return $this->output->renderTwigTemplate('UserProfile.twig', [
            "user" => $user,
            "user_given" => $autofill_preferred_name[0],
            "user_family" => $autofill_preferred_name[1],
            "change_name_text" => $change_name_text,
            "show_change_password" => $database_authentication,
            "csrf_token" => $csrf_token,
            "access_level" => Access::ACCESS_LEVELS[$user->getAccessLevel()],
            "display_access_level" => $user->accessFaculty(),
            "change_password_url" => $this->output->buildUrl(['user_profile', 'change_password']),
            'available_time_zones' => implode(',', DateUtils::getOrderedTZWithUTCOffset()),
            'user_time_zone_with_offset' => $user_time_zone_with_offset,
            'user_utc_offset' => $user_utc_offset
        ]);
    }
}
