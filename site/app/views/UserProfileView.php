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
        $autofill_preferred_name = [$user->getLegalFirstName(), $user->getLegalLastName()];
        if ($user->getPreferredFirstName() != "") {
            $autofill_preferred_name[0] = $user->getPreferredFirstName();
        }
        if ($user->getPreferredLastName() != "") {
            $autofill_preferred_name[1] = $user->getPreferredLastName();
        }

        $user_initial_option = $user->getPreferredLastNameInitial();

        $last_name_split_by_space = explode(" ", $autofill_preferred_name[1]);
        $last_name_split_by_hyphen = explode("-", $autofill_preferred_name[1]);
        $is_last_name_space_long = count($last_name_split_by_space) >= 2;
        $is_last_name_hyphen_long = count($last_name_split_by_hyphen) >= 2;

        $parse_initial = function (str $c): str {
            return $c[0];
        };
        $user_last_name_initial = "";
        if ($user_initial_option == 0) {
            $user_last_name_initial = $autofill_preferred_name[1][0];
        }
        elseif ($user_initial_option == 1 && $is_last_name_space_long) {
            $user_last_name_initial = implode(" ", array_map($parse_initial, $last_name_split_by_space));
        }
        elseif ($user_initial_option == 2 && $is_last_name_hyphen_long) {
            $user_last_name_initial = implode("-", array_map($parse_initial, $last_name_split_by_hyphen));
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
            "user_first" => $autofill_preferred_name[0],
            "user_last" => $autofill_preferred_name[1],
            "user_lastname_initial" => $user_last_name_initial,
            "user_initial_option" => $user_initial_option,
            "is_last_name_space_long" => $is_last_name_space_long,
            "is_last_name_hyphen_long" => $is_last_name_hyphen_long,
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
