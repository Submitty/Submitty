<?php

namespace app\controllers;


use app\authentication\DatabaseAuthentication;
use app\libraries\Core;
use app\libraries\response\MultiResponse;
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
}
