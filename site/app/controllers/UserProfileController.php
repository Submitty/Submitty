<?php

namespace app\controllers;

use app\authentication\DatabaseAuthentication;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\views\grading\ImagesView;
use phpDocumentor\Reflection\Types\Boolean;
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
     * @Route("/user_profile", methods={"GET"})
     * @return MultiResponse
     */
    public function showUserProfile() {
        $this->core->getOutput()->addBreadcrumb("My Profile");
        return new MultiResponse(
            null,
            new WebResponse(
                ['UserProfile'],
                'showUserProfile',
                $this->core->getUser(),
                $this->core->getConfig()->getUsernameChangeText(),
                $this->core->getAuthentication() instanceof DatabaseAuthentication,
                $this->core->getCsrfToken()
            )
        );
    }

    /**
     * @Route("/user_profile/change_time_zone", methods={"POST"})
     * @return JsonResponse
     *
     * Handle ajax request to update the currently logged in user's time zone data.
     *
     * Will return a json success or failure response depending on the result of the operation.
     */
    public function changeTimeZone() {
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

    /**
     * @Route("/user_profile/change_password", methods={"POST"})
     * @return MultiResponse
     */
    public function changePassword() {
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


    /**
     * @Route("/user_profile/change_preferred_names", methods={"POST"})
     * @return JsonResponse
     * @throws \ImagickException
     */
    public function changeUserName() {
        $user = $this->core->getUser();
        if (empty($_POST['user_firstname_change']) && empty($_POST['user_lastname_change'])) {
            $newFirstName = trim($_POST['first_name']);
            $newLastName = trim($_POST['last_name']);

            // validateUserData() checks both for length (not to exceed 30) and for valid characters.
            if ($user->validateUserData('user_preferred_firstname', $newFirstName) === true && $user->validateUserData('user_preferred_lastname', $newLastName) === true) {
                $user->setPreferredFirstName($newFirstName);
                $user->setPreferredLastName($newLastName);
                //User updated flag tells auto feed to not clobber some of the user's data.
                $user->setUserUpdated(true);
                $this->core->getQueries()->updateUser($user);
                return JsonResponse::getSuccessResponse([
                    'message' => "Preferred names updated successfully!",
                    'first_name' => $newFirstName,
                    'last_name' => $newLastName
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

    /**
     * @Route("/user_profile/change_profile_photo", methods={"POST"})
     * @return JsonResponse
     * @throws \ImagickException
     */
    public function changeProfilePhoto() {
        $user = $this->core->getUser();
        // No image uploaded
        if (empty($_FILES['user_image']) || empty($_FILES['user_image']['tmp_name'])) {
            return JsonResponse::getErrorResponse('No image uploaded to update the profile photo');
        }
        else {
            $meta = explode('.', $_FILES['user_image']['name']);
            $extension = $meta[1];

            // Save image for user
            $result = $user->setDisplayImage($extension, $_FILES['user_image']['tmp_name']);
            $display_image = $user->getDisplayImage();

            if (!$result) {
                return JsonResponse::getErrorResponse('Something went wrong while updating your profile photo.');
            }
            else {
                // image_data and mime_type will be set but be sure that code doesnt break check for null exception
                return JsonResponse::getSuccessResponse([
                    'message' => 'Profile photo updated successfully!',
                    'image_data' => !is_null($display_image) ? $display_image->getImageBase64MaxDimension(200) : '',
                    'image_mime_type' => !is_null($display_image) ? $display_image->getMimeType() : '',
                    'image_alt_data' => $user->getDisplayedFirstName() . ' ' . $user->getDisplayedLastName(),
                    'image_flagged_state' => $user->getDisplayImageState(),
                ]);
            }
        }
    }

    /**
     * @Route("/user_profile/change_secondary_email", methods={"POST"})
     * @return JsonResponse
     * @throws \ImagickException
     */
    public function changeSecondaryEmail() {
        $user = $this->core->getUser();

        if(!empty($_POST['secondary_email']) && !empty($_POST['secondary_email_notify'])){
            $secondaryEmail = trim($_POST['secondary_email']);
            $secondaryEmailNotify = trim($_POST['secondary_email_notify']);

            if($user->validateUserData('user_email_secondary', $secondaryEmail) === true){
                $user->setSecondaryEmail($secondaryEmail);
                $user->setEmailBoth($secondaryEmailNotify === "true");
                $user->setUserUpdated(true);
                $this->core->getQueries()->updateUser($user);
                return JsonResponse::getSuccessResponse([
                    'message' => 'Secondary email address updated successfully',
                    'secondary_email' => $secondaryEmail,
                    'secondary_email_notify' => $secondaryEmailNotify
                ]);
            }
            else{
                return JsonResponse::getErrorResponse("Secondary email address must be a valid email and the notify setting must be a boolean");
            }
        }
        else{
            return JsonResponse::getErrorResponse("Secondary email and secondary email notify must both be set");
        }
    }
}
