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
     * @param bool $database_authentication
     * @param string $csrf_token
     * @return string
     */
    public function showUserProfile(
        User $user,
        bool $database_authentication,
        string $csrf_token
    ) {
        $this->output->addInternalJs('user-profile.js');
        $this->output->addInternalCss('user-profile.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->output->setPageName('My Profile');
        $this->output->addSelect2WidgetCSSAndJs(); /* Adding select2 CSS and JS widgets*/

        $user_utc_offset = DateUtils::getUTCOffset($user->getTimeZone());
        $user_time_zone_with_offset = $user_utc_offset === 'NOT SET'
            ?  $user->getTimeZone()
            : "(UTC" . $user_utc_offset . ") " . $user->getTimeZone();

        $this->core->getOutput()->addInternalModuleJs('user-profile.js');
        $this->core->getOutput()->addInternalModuleJs('notifications.js');

        $curr_locale = $this->core->getConfig()->getLocale()->getName();
        $supported_locales = $this->core->getSupportedLocales() ?? [];
        $locale_names = array_map(fn(string $locale): string => mb_convert_case(\Locale::getDisplayName($locale, $curr_locale), MB_CASE_TITLE, 'UTF-8'), $supported_locales);
        $supported_locales = array_combine($supported_locales, $locale_names);
        $default_locale_name = mb_convert_case(\Locale::getDisplayName($this->core->getConfig()->getDefaultLocaleName(), $curr_locale), MB_CASE_TITLE, 'UTF-8');

        return $this->output->renderTwigTemplate('UserProfile.twig', [
            "user" => $user,
            "user_given" => $user->getPreferredGivenName() ?? "",
            "user_family" => $user->getPreferredFamilyName() ?? "",
            "user_pronouns" => $user->getPronouns(),
            "display_pronouns" => $user->getDisplayPronouns(),
            "show_change_password" => $database_authentication,
            "csrf_token" => $csrf_token,
            "access_level" => Access::ACCESS_LEVELS[$user->getAccessLevel()],
            "display_access_level" => $user->accessFaculty(),
            "change_password_url" => $this->output->buildUrl(['user_profile', 'change_password']),
            'available_time_zones' => implode(',', DateUtils::getOrderedTZWithUTCOffset()),
            'user_time_zone_with_offset' => $user_time_zone_with_offset,
            'user_utc_offset' => $user_utc_offset,
            'supported_locales' => $supported_locales,
            'default_locale_name' => $default_locale_name
        ]);
    }
}
