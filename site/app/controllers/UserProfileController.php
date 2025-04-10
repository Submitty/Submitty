<?php

namespace app\controllers;

use app\authentication\DatabaseAuthentication;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\FileUtils;
use app\models\User;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserProfileController
 * @package app\controllers
 */
class UserProfileController extends AbstractController {
    /**
     * UserProfileController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }
    /**
     * Show User's profile data
     *
     */
    #[Route("/user_profile", methods: ["GET"])]
    public function showUserProfile(): MultiResponse {
        $this->core->getOutput()->addBreadcrumb("My Profile");
        return new MultiResponse(
            null,
            new WebResponse(
                ['UserProfile'],
                'showUserProfile',
                $this->core->getUser(),
                $this->core->getAuthentication() instanceof DatabaseAuthentication,
                $this->core->getCsrfToken()
            )
        );
    }

    /**
     *
     * Handle ajax request to update the currently logged in user's time zone data.
     *
     * Will return a json success or failure response depending on the result of the operation.
     */
    #[Route("/user_profile/change_time_zone", methods: ["POST"])]
    public function changeTimeZone(): JsonResponse {
        if (isset($_POST['time_zone'])) {
            $updated = $this->core->getUser()->setTimeZone($_POST['time_zone']);

            // Updating went smoothly, so return success
            if ($updated) {
                $offset = DateUtils::getUTCOffset($_POST['time_zone']);
                $user_time_zone_with_offset = $offset === "NOT SET"
                    ? $this->core->getUser()->getTimeZone()
                    : "(UTC" . $offset . ") " . $this->core->getUser()->getTimeZone();
                return JsonResponse::getSuccessResponse([
                    'utc_offset' => $offset,
                    'user_time_zone_with_offset' => $user_time_zone_with_offset
                ]);
            }
        }

        // Some failure occurred
        return JsonResponse::getFailResponse('Error encountered updating user time zone.');
    }

    #[Route("/user_profile/set_pref_locale", methods: ["POST"])]
    public function setPrefLocale(): JsonResponse {
        if (isset($_POST['locale'])) {
            $user = $this->core->getUser();
            $user->setPreferredLocale(empty($_POST['locale']) ? null : $_POST['locale']);
            return JsonResponse::getSuccessResponse([ 'locale' => $user->getPreferredLocale() ]);
        }

        return JsonResponse::getFailResponse('Failed to update user locale.');
    }

    #[Route("/user_profile/change_password", methods: ["POST"])]
    public function changePassword(): MultiResponse {
        $user = $this->core->getUser();
        if (
            !empty($_POST['new_password'])
            && !empty($_POST['confirm_new_password'])
            && $_POST['new_password'] == $_POST['confirm_new_password']
        ) {
            $user->setPassword($_POST['new_password']);
            $this->core->getQueries()->updateUser($user);
            $this->core->addSuccessMessage("Updated password");
        }
        else {
            $this->core->addErrorMessage("Must put same password in both boxes.");
        }
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildUrl(['home']))
        );
    }

    #[Route("/user_profile/change_pronouns", methods: ["POST"])]
    public function changePronouns(): JsonResponse {
        $user = $this->core->getUser();
        if (isset($_POST['pronouns'])) {
            $newPronouns = trim($_POST['pronouns']);
            $newDisplayPronouns = filter_var($_POST['pronouns-forum-display'], FILTER_VALIDATE_BOOLEAN);
            //validPronouns() checks for valid option
            if ($user->validateUserData('user_pronouns', $newPronouns) === true) {
                $user->setPronouns($newPronouns);
                $user->setDisplayPronouns($newDisplayPronouns);
                $user->setUserUpdated(true);
                $this->core->getQueries()->updateUser($user);
                return JsonResponse::getSuccessResponse([
                    'message' => "Pronouns updated successfully",
                    'pronouns' => $newPronouns,
                    'display_pronouns' => $newDisplayPronouns,
                ]);
            }
            else {
                return JsonResponse::getErrorResponse("Pronouns are not valid");
            }
        }
        else {
            return JsonResponse::getErrorResponse("Pronouns does not exist");
        }
    }

    #[Route("/user_profile/change_display_name_order", methods: ["POST"])]
    public function changeDisplayNameOrder(): JsonResponse {
        $user = $this->core->getUser();
        if (isset($_POST['display-name-order'])) {
            $newDisplayNameOrdering = trim($_POST['display-name-order']);
            $user->setDisplayNameOrder($newDisplayNameOrdering);
            $user->setUserUpdated(true);
            $this->core->getQueries()->updateUser($user);
            return JsonResponse::getSuccessResponse([
                'message' => "Name Order updated successfully",
                'display-name-order' => $newDisplayNameOrdering,
            ]);
        }
        else {
            return JsonResponse::getErrorResponse("Name Order is incorrect");
        }
    }

    #[Route("/user_profile/change_preferred_names", methods: ["POST"])]
    public function changeUserName(): JsonResponse {
        $user = $this->core->getUser();
        if (isset($_POST['given_name']) && isset($_POST['family_name'])) {
            $newGivenName = trim($_POST['given_name']);
            $newFamilyName = trim($_POST['family_name']);

            // validateUserData() checks both for length (not to exceed 30) and for valid characters.
            if ($user->validateUserData('user_preferred_givenname', $newGivenName) === true && $user->validateUserData('user_preferred_familyname', $newFamilyName) === true) {
                if (
                    (($newGivenName === ""
                    && $user->getPreferredGivenName() === null)
                    || $newGivenName === $user->getDisplayedGivenName())
                    && (($newFamilyName === ""
                    && $user->getPreferredFamilyName() === null)
                    || $newFamilyName === $user->getDisplayedFamilyName())
                ) {
                    return JsonResponse::getErrorResponse("No changes detected to update preferred names!");
                }
                if ($newGivenName === "" || $newGivenName === $user->getLegalGivenName()) {
                    $user->setPreferredGivenName(null);
                }
                else {
                    $user->setPreferredGivenName($newGivenName);
                }
                if ($newFamilyName === "" || $newFamilyName === $user->getLegalFamilyName()) {
                    $user->setPreferredFamilyName(null);
                }
                else {
                    $user->setPreferredFamilyName($newFamilyName);
                }
                //User updated flag tells auto feed to not clobber some of the user's data.
                $user->setUserUpdated(!is_null($user->getPreferredGivenName()) || !is_null($user->getPreferredFamilyName()));
                $this->core->getQueries()->updateUser($user);
                return JsonResponse::getSuccessResponse([
                    'message' => "Preferred names updated successfully!",
                    'displayed_given_name' => $user->getDisplayedGivenName(),
                    'displayed_family_name' => $user->getDisplayedFamilyName(),
                    'preferred_given_name' => $user->getPreferredGivenName() ?? "",
                    'preferred_family_name' => $user->getPreferredFamilyName() ?? "",
                    'abbreviation_options' => implode('|', array_map(fn($i) => $user->getDisplayAbbreviatedName($i), range(0, 3))),
                    'current_abbreviation' => $user->getDisplayAbbreviatedName()
                ]);
            }
            else {
                return JsonResponse::getErrorResponse("Preferred names must not exceed 30 chars.  Letters, spaces, hyphens, apostrophes, periods, parentheses, and backquotes permitted.");
            }
        }
        else {
            return JsonResponse::getErrorResponse('Preferred names cannot be empty!');
        }
    }

    #[Route("/user_profile/update_last_initial_format", methods: ["POST"])]
    public function updateLastInitialFormat(): JsonResponse {
        $user = $this->core->getUser();
        if (isset($_POST['format'])) {
            $newVal = intval($_POST['format']);
            // Handle the case where intval returns zero due to an invalid string
            if ($newVal !== 0 || $_POST['format'] === '0' || $_POST['format'] === 0) {
                try {
                    $user->setLastInitialFormat($newVal);
                    $user->setUserUpdated(true);
                    $this->core->getQueries()->updateUser($user);
                    return JsonResponse::getSuccessResponse([
                        'message' => "Last initial format successfully updated!",
                        'format' => $user->getLastInitialFormat(),
                        'display_format' => $user->getDisplayLastInitialFormat(),
                        'new_abbreviated_name' => $user->getDisplayAbbreviatedName()
                    ]);
                }
                catch (\InvalidArgumentException $e) {
                }
            }
        }
        return JsonResponse::getErrorResponse("Invalid option for last initial format!");
    }

    /**
     * @throws \ImagickException
     */
    #[Route("/user_profile/change_profile_photo", methods: ["POST"])]
    public function changeProfilePhoto(): JsonResponse {
        $user = $this->core->getUser();
        // No image uploaded
        if (empty($_FILES['user_image']) || empty($_FILES['user_image']['tmp_name'])) {
            return JsonResponse::getErrorResponse('No image uploaded to update the profile photo');
        }
        else {
            preg_match("/^.*\.(jpg|jpeg|png|gif)$/i", $_FILES['user_image']['name'], $extension);
            if (!(FileUtils::isValidImage($_FILES['user_image']['tmp_name']) && FileUtils::validateUploadedFiles($_FILES['user_image'])[0]['success'] && (count($extension) >= 2) && $_FILES['user_image']['size'] <= 5 * 1048576)) {
                return JsonResponse::getErrorResponse("Something's wrong with the uploaded file.");
            }

            // Save image for user
            $result = $user->setDisplayImage($extension[1], $_FILES['user_image']['tmp_name']);
            $display_image = $user->getDisplayImage();
            if ($result === User::PROFILE_IMG_QUOTA_EXHAUSTED) {
                return JsonResponse::getErrorResponse('You have exhausted the quota for number of profile photos, kindly contact the system administrator to resolve this.');
            }

            if ($result === User::PROFILE_IMG_SET_FAILURE) {
                return JsonResponse::getErrorResponse('Something went wrong while updating your profile photo.');
            }
            else {
                // image_data and mime_type will be set but be sure that code doesn't break check for null exception
                return JsonResponse::getSuccessResponse([
                    'message' => 'Profile photo updated successfully!',
                    'image_data' => !is_null($display_image) ? $display_image->getImageBase64MaxDimension(200) : '',
                    'image_mime_type' => !is_null($display_image) ? $display_image->getMimeType() : '',
                    'image_alt_data' => $user->getDisplayedGivenName() . ' ' . $user->getDisplayedFamilyName(),
                    'image_flagged_state' => $user->getDisplayImageState(),
                ]);
            }
        }
    }

    #[Route("/user_profile/change_secondary_email", methods: ["POST"])]
    public function changeSecondaryEmail(): JsonResponse {
        $user = $this->core->getUser();

        if (isset($_POST['secondary_email']) && isset($_POST['secondary_email_notify'])) {
            $secondaryEmail = trim($_POST['secondary_email']);
            $secondaryEmailNotify = trim($_POST['secondary_email_notify']) === "true";
            if ((!$secondaryEmailNotify && $secondaryEmail === "") || (($secondaryEmail !== "") && $user->validateUserData('user_email_secondary', $secondaryEmail) === true)) {
                $user->setSecondaryEmail($secondaryEmail);
                $user->setEmailBoth($secondaryEmailNotify);
                $this->core->getQueries()->updateUser($user);
                return JsonResponse::getSuccessResponse([
                    'message' => 'Secondary email address updated successfully',
                    'secondary_email' => $secondaryEmail,
                    'secondary_email_notify' => $secondaryEmailNotify ? 'True' : 'False'
                ]);
            }
            else {
                if ($secondaryEmail === "") {
                    return JsonResponse::getErrorResponse("Secondary email can't be empty if secondary email notify is true");
                }
                return JsonResponse::getErrorResponse("Secondary email address must be a valid email");
            }
        }
        else {
            return JsonResponse::getErrorResponse("Secondary email and secondary email notify must both be set");
        }
    }
}
