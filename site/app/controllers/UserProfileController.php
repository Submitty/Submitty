<?php

namespace app\controllers;


use app\authentication\DatabaseAuthentication;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
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
     * @Route("/courses/{_semester}/{_course}/user-profile", methods={"GET"})
     * @return MultiResponse
     */
    public function showUserProfile() {

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
     * @Route("/courses/{_semester}/{_course}/current-user/change-time-zone", methods={"POST"})
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
                return JsonResponse::getSuccessResponse(['utc_offset' => $offset]);
            }
        }

        // Some failure occurred
        return JsonResponse::getFailResponse('Error encountered updating user time zone.');
    }

    /**
     * @Route("/courses/{_semester}/{_course}/current-user/change-password", methods={"POST"})
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
     * @Route("/courses/{_semester}/{_course}/current-user/change-username", methods={"POST"})
     * @return MultiResponse
     * @throws \ImagickException
     */
    public function changeUserName() {
        $user = $this->core->getUser();
        if (isset($_POST['user_firstname_change']) && isset($_POST['user_lastname_change'])) {
            $newFirstName = trim($_POST['user_firstname_change']);
            $newLastName = trim($_POST['user_lastname_change']);

            // validateUserData() checks both for length (not to exceed 30) and for valid characters.
            if ($user->validateUserData('user_preferred_firstname', $newFirstName) === true && $user->validateUserData('user_preferred_lastname', $newLastName) === true) {
                $user->setPreferredFirstName($newFirstName);
                $user->setPreferredLastName($newLastName);
                //User updated flag tells auto feed to not clobber some of the user's data.
                $user->setUserUpdated(true);
                $this->core->getQueries()->updateUser($user);
            }
            else {
                $this->core->addErrorMessage("Preferred names must not exceed 30 chars.  Letters, spaces, hyphens, apostrophes, periods, parentheses, and backquotes permitted.");
            }

            // If we received an image file attempt to save it
            if ($_FILES['user_image']['tmp_name'] !== '') {
                $meta = explode('.', $_FILES['user_image']['name']);
                $file_name = $meta[0];
                $extension = $meta[1];

                // Save image for user
                $result = $user->setDisplayImage($extension, $_FILES['user_image']['tmp_name']);

                if (!$result) {
                    $this->core->addErrorMessage('Some error occurred saving your new user image.');
                }
            }
        }
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['user-profile']))
        );
    }
}
