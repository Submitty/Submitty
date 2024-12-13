<?php

namespace app\controllers;

use app\authentication\SamlAuthentication;
use app\entities\VcsAuthToken;
use app\libraries\Core;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\Utils;
use app\libraries\Logger;
use app\libraries\response\MultiResponse;
use app\views\AuthenticationView;
use app\models\User;
use app\models\Email;
use app\repositories\VcsAuthTokenRepository;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthenticationController
 *
 * Controller to deal with user authentication and de-authentication. The actual lifting is done through Core
 * and the associated registered IAuthentication interface returning whether or not we were able to actually
 * authenticate the user or not, and then the controller redirects on that answer.
 */
class AuthenticationController extends AbstractController {
    /**
     * @var bool Is the user logged in or not. We use this to prevent the user going to the login controller
     *           and trying to login again.
     */
    private $logged_in;

    /**
     * AuthenticationController constructor.
     *
     * @param Core $core
     * @param bool $logged_in
     */
    public function __construct(Core $core, $logged_in = false) {
        parent::__construct($core);
        $this->logged_in = $logged_in;
    }

    /**
     * Logs out the current user from the system. This is done by both deleting the current going
     * session from the database as well as invalidating the session id saved in the cookie. The latter
     * is not strictly necessary, but still good to tidy up.
     *
     * @return MultiResponse
     */
    #[Route("/authentication/logout")]
    public function logout() {
        if ($this->core->removeCurrentSession()) {
            Logger::logAccess($this->core->getUser()->getId(), $_COOKIE['submitty_token'], "logout");
        }

        Utils::setCookie('submitty_session', '', time() - 3600);
        // Remove all history for checkpoint gradeables
        foreach (array_keys($_COOKIE) as $cookie) {
            if (strpos($cookie, "_history") == strlen($cookie) - 8) { // '_history' is len 8
                Utils::setCookie($cookie, '', time() - 3600);
            }
        }


        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildUrl(['authentication', 'login']))
        );
    }

    /**
     * Display the login form to the user
     *
     * @var string $old the url to redirect to after login
     * @return ResponseInterface
     */
    #[Route("/authentication/login")]
    public function loginForm($old = null) {
        if (!is_null($old) && !str_starts_with(urldecode($old), $this->core->getConfig()->getBaseUrl())) {
            $old = null;
        }
        $is_saml_auth = $this->core->getAuthentication() instanceof SamlAuthentication;
        return new WebResponse('Authentication', 'loginForm', $old, $is_saml_auth);
    }

    /**
     * Checks the submitted login form via the configured "authentication" setting. Additionally, on successful
     * login, we want to redirect the user $_REQUEST the page they were attempting to goto before being sent to the
     * login form (this being saved in the $_POST['old'] array). However, on failure to login, we want to continue
     * to maintain that old request data passing it back into the login form.
     *
     * @var string $old the url to redirect to after login
     * @return MultiResponse
     */
    #[Route("/authentication/check_login")]
    public function checkLogin($old = null) {
        $is_saml_auth = $this->core->getAuthentication() instanceof SamlAuthentication;
        if (!is_null($old) && !str_starts_with(urldecode($old), $this->core->getConfig()->getBaseUrl())) {
            $old = null;
        }
        if (isset($old)) {
            $old = urldecode($old);
        }
        if ($this->logged_in) {
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($old)
            );
        }
        $_POST['stay_logged_in'] = (isset($_POST['stay_logged_in']));
        if (!$is_saml_auth) {
            if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
                $msg = 'Cannot leave user id or password blank';

                $this->core->addErrorMessage($msg);
                return new MultiResponse(
                    JsonResponse::getFailResponse($msg),
                    null,
                    new RedirectResponse($old)
                );
            }
            $this->core->getAuthentication()->setUserId($_POST['user_id']);
            $this->core->getAuthentication()->setPassword($_POST['password']);
        }
        if ($this->core->authenticate($_POST['stay_logged_in'] || $is_saml_auth) === true) {
            if ($is_saml_auth && isset($_POST['RelayState']) && str_starts_with($_POST['RelayState'], $this->core->getConfig()->getBaseUrl())) {
                $old = $_POST['RelayState'];
            }
            Logger::logAccess($this->core->getAuthentication()->getUserId(), $_COOKIE['submitty_token'], "login");
            $msg = "Successfully logged in as " . htmlentities($this->core->getAuthentication()->getUserId());

            $this->core->addSuccessMessage($msg);
            return new MultiResponse(
                JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]),
                null,
                new RedirectResponse($old)
            );
        }
        else {
            if ($is_saml_auth) {
                $old = $this->core->buildUrl(['authentication', 'login']);
                $msg = "Could not login";
            }
            else {
                $msg = "Could not login using that user id or password";
                $this->core->addErrorMessage($msg);
            }

            $this->core->redirect($old);
            return new MultiResponse(
                JsonResponse::getFailResponse($msg),
                null,
                new RedirectResponse($old)
            );
        }
    }

    /**
     * @return MultiResponse
     */
    #[Route("/api/token", methods: ["POST"])]
    public function getToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $token = $this->core->authenticateJwt();
        if ($token) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['token' => $token]));
        }
        else {
            $msg = "Could not login using that user id or password";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }

    /**
     *
     * @return MultiResponse
     */
    #[Route("/api/token/invalidate", methods: ["POST"])]
    public function invalidateToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $success = $this->core->invalidateJwt();
        if ($success) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
        }
        else {
            $msg = "Could not login using that user id or password";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }

    /**
     * Handle stateless authentication for the VCS endpoints.
     *
     * This endpoint is unique from the other authentication methods in
     * that this requires a specific course so that we can check a user's
     * status, as well as potentially information about a particular
     * gradeable in that course.
     *
     * @return MultiResponse
     */
    #[Route("{_semester}/{_course}/authentication/vcs_login")]
    public function vcsLogin() {
        if (
            empty($_POST['user_id'])
            || empty($_POST['password'])
            || empty($_POST['gradeable_id'])
            || empty($_POST['id'])
            || !$this->core->getConfig()->isCourseLoaded()
        ) {
            $msg = 'Missing value for one of the fields';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }

        $token_login_success = false;
        $em = $this->core->getSubmittyEntityManager();
        /** @var VcsAuthTokenRepository $repo */
        $repo = $em->getRepository(VcsAuthToken::class);
        $tokens = $repo->getAllByUser($_POST['user_id']);

        foreach ($tokens as $token) {
            if (password_verify($_POST['password'], $token->getToken())) {
                $token_login_success = true;
                break;
            }
        }

        if (!$token_login_success) {
            $this->core->getAuthentication()->setUserId($_POST['user_id']);
            $this->core->getAuthentication()->setPassword($_POST['password']);
            if ($this->core->getAuthentication()->authenticate() !== true) {
                $msg = "Could not login using that user id or password";
                return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
            }
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);
        if ($user === null) {
            $msg = "Could not find that user for that course";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        elseif ($user->accessFullGrading()) {
            $msg = "Successfully logged in as {$_POST['user_id']}";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]));
        }

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['gradeable_id']);
        }
        catch (\InvalidArgumentException $exc) {
            $gradeable = null;
        }

        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            if (!$this->core->getQueries()->getTeamById($_POST['id'])->hasMember($_POST['user_id'])) {
                $msg = "This user is not a member of that team.";
                return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
            }
        }
        elseif ($_POST['user_id'] !== $_POST['id']) {
            $msg = "This user cannot check out that repository.";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }

        $msg = "Successfully logged in as {$_POST['user_id']}";
        return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]));
    }

    /**
     * @param string|null $old
     *
     * @return RedirectResponse
     */
    #[Route("/authentication/saml_start")]
    public function samlStart(string $old = null): RedirectResponse {
        if (!$this->core->getAuthentication() instanceof SamlAuthentication) {
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        if (!is_null($old) && !str_starts_with(urldecode($old), $this->core->getConfig()->getBaseUrl())) {
            $old = null;
        }
        /** @var SamlAuthentication $auth */
        $auth = $this->core->getAuthentication();
        return new RedirectResponse($auth->redirect($old));
    }

    /**
     *
     * @return ResponseInterface
     */
    #[Route("/authentication/user_select")]
    public function userSelection() {
        if (!isset($_SESSION['Authenticated_User_Id']) || !$this->core->getAuthentication() instanceof SamlAuthentication) {
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        $authorized_users = array_map(function ($user) {
            return $user['user_id'];
        }, $this->core->getQueries()->getSAMLAuthorizedUserIDs($_SESSION['Authenticated_User_Id']));
        $users = $this->core->getQueries()->getUsersByIds($authorized_users);

        return new WebResponse(AuthenticationView::class, 'userSelection', $users);
    }

    /**
     * Check if password has at least one of the following, Upper case letter, Lower case letter, Special character, and number
     */
    public function checkChars(string $password): bool {
        $upperCase = preg_match('/[A-Z]/', $password);
        $lowerCase = preg_match('/[a-z]/', $password);
        $specialChar = preg_match('/[^A-Za-z0-9]/', $password);
        $numericVal = preg_match('/[0-9]/', $password);
        return $upperCase >= 1 && $lowerCase >= 1 && $specialChar >= 1 && $numericVal >= 1;
    }

    /**
     * Check if the user ID meets requirements
     */
    public function isAcceptedUserId(string $user_id, string $given_name, string $family_name, string $email): bool {
        $requirements = $this->core->getConfig()->getUserIdRequirements();

        if ($requirements['max_length'] < strlen($user_id) || $requirements['min_length'] > strlen($user_id)) {
            return false;
        }

        if ($requirements['all'] === true) {
            return true;
        }
        elseif ($requirements['require_name'] === true) {
            $name_requirements = $requirements['name_requirements'];
            $given_first = $name_requirements['given_first'] === 'true';

            $id_given_name = substr($user_id, ($given_first ? 0 : $name_requirements['family_name']), ($given_first ? $name_requirements['given_name'] : strlen($user_id)));
            $id_family_name = substr($user_id, ($given_first ? $name_requirements['given_name'] : 0), ($given_first ? strlen($user_id) : $name_requirements['family_name']));
            $is_given_name = (strtolower($id_given_name) === substr(strtolower($given_name), 0, $name_requirements['given_name']));
            $is_family_name = (strtolower($id_family_name) === substr(strtolower($family_name), 0, $name_requirements['family_name']));
            if ($is_family_name && $is_given_name) {
                return true;
            }
            return false;
        }
        elseif ($requirements['require_email'] === true) {
            if ($requirements['email_requirements']['whole_email']) {
                return $user_id === $email;
            }
            elseif ($requirements['email_requirements']['whole_prefix']) {
                $split_email = explode('@', $email);
                $email_extension = array_pop($split_email);
                return $user_id === implode('', $split_email);
            }
            else {
                return substr($user_id, 0, $requirements['email_requirements']['prefix_count']) === substr($email, 0, $requirements['email_requirements']['prefix_count']);
            }
        }
        else {
            return false;
        }
    }

    /**
     * Returns true if the password is greater than or equal to 12 characters, and has the required characters
     */
    public function isGoodPassword(string $password): bool {
        return strlen($password) >= 12 && $this->checkChars($password);
    }

    /**
     * Checks if the email extension is in the accepted emails part of the Submitty config file
     */
    public function isAcceptedEmail(string $email): bool {
        $emails = $this->core->getConfig()->getAcceptedEmails();
        // Check if the file was read successfully
        try {
            $split_email = explode('@', $email);
            $email_extension = $split_email[count($split_email) - 1];
        }
        catch (\Error $error) {
            return false;
        }
        return in_array($email_extension, array_keys($emails), true);
    }

    /**
     * @return array<mixed>
     */
    public function generateVerificationCode(): array {
        $code = $this->core->getConfig()->isDebug() ? '00000000' : Utils::generateRandomString();
        $timestamp = time() + 60 * 15; // 15 minutes from now, may eventually set this as a configurable value.
        return ['code' => strval($code), 'exp' => $timestamp];
    }

    public function sendVerificationEmail(string $email, string $verification_code, string $user_id): void {
        $subject = "Submitty Email Verification";
        $url = $this->core->getConfig()->getBaseUrl() . 'authentication/verify_email?verification_code=' . $verification_code;
        $body = <<<EMAIL
Welcome to Submitty! We are excited to have you on board. To complete your account setup, either enter this verification code, or click the link below.

Verification Code: $verification_code

Verification Link: $url

If you didn't sign up for Submitty, you can ignore this email.

Welcome,
Submitty Team
EMAIL;

        $details = [
            'subject' => $subject,
            'body' => $body,
            'email_address' => $email,
            'to_name' => $user_id
        ];
        $email = new Email($this->core, $details);
        $this->core->getNotificationFactory()->sendEmails([$email]);
    }

    /**
     * Display the form for creating a new account
     */
    #[Route("/authentication/create_account", methods: ['GET'])]
    public function signupForm(): ResponseInterface {
        // Check if the user is already logged in, if yes, redirect to home or another appropriate page
        if ($this->logged_in) {
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
        if (!$this->core->getConfig()->isUserCreateAccount()) {
            $this->core->addErrorMessage('Users cannot create their own account, Please have your system administrator add you.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        return new WebResponse('Authentication', 'signupForm', ['email' => $this->core->getConfig()->getAcceptedEmails(), 'user_id' => $this->core->getConfig()->getUserIdRequirements()]);
    }

    /**
     * Display the form for creating a new account
     */
    #[Route("/authentication/email_verification")]
    public function showVerifyEmailForm(): ResponseInterface {
        // Check if the user is already logged in, if yes, redirect to home or another appropriate page
        if (!$this->core->getConfig()->isUserCreateAccount()) {
            $this->core->addErrorMessage('Users cannot create their own account, Please have your system administrator add you.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        return new WebResponse('Authentication', 'verificationForm');
    }

     /**
     * Display the form for creating a new account
     */
    #[Route("/authentication/resend_email")]
    public function resendVerificationEmail(): ResponseInterface {
        // Check if the user is already logged in, if yes, redirect to home or another appropriate page
        if ($this->logged_in) {
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
        if (!$this->core->getConfig()->isUserCreateAccount()) {
            $this->core->addErrorMessage('Users cannot create their own account, Please have your system administrator add you.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        if (!isset($_GET['email'])) {
            $this->core->addErrorMessage('You must specify an email to send the verification to.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'email_verification']));
        }
        $unverified_users = $this->core->getQueries()->getUnverifiedUserIdEmailExists($_GET['email'], '');
        if (count($unverified_users) === 0) {
            $this->core->addErrorMessage('Either you have already verified your email, or that email is not associated with an account.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }
        $verification_values = $this->generateVerificationCode();
        $this->core->getQueries()->updateUserVerificationValues($_GET['email'], $verification_values['code'], $verification_values['exp']);
        $this->sendVerificationEmail($_GET['email'], $verification_values['code'], $unverified_users['user_id']);
        $this->core->addSuccessMessage('Verification email resent.');
        return new RedirectResponse($this->core->buildUrl(['authentication', 'email_verification']));
    }

    #[Route("/authentication/verify_email")]
    public function verifyEmail(): RedirectResponse {
        // Check if the user is already logged in, if yes, redirect to home or another appropriate page
        if (!$this->core->getConfig()->isUserCreateAccount()) {
            $this->core->addErrorMessage('Users cannot create their own account, Please have your system administrator add you.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }

        $verification_values = $this->core->getQueries()->getUserVerificationValuesByCode($_GET['verification_code']);

        if ($verification_values === []) {
            $this->core->addErrorMessage('The verification code is not correct, please resend email verification.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'email_verification']));
        }

        $this->core->addSuccessMessage('You have successfully verified your email.');
        $user = $this->core->getQueries()->getUnverifiedUserByCode($_GET['verification_code']);
        $this->core->getQueries()->insertSubmittyUser($user);
        $this->core->getQueries()->removeUnverifiedUserByCode($_GET['verification_code'], $user->getId());
        return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
    }

    /**
     * Handles the submission of the new account creation form
     */
    #[Route("/authentication/self_add_user")]
    public function addNewUser(): RedirectResponse {
        // Check if the user is already logged in, if yes, redirect to home or another appropriate page
        if ($this->logged_in) {
            return new RedirectResponse($this->core->buildUrl(['home']));
        }

        // Should never happen, however they can visit this URL manually, so this is to prevent unwanted account creation.
        if (!$this->core->getConfig()->isUserCreateAccount()) {
            $this->core->addErrorMessage('Users cannot create their own account, Please have your system administrator add you.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'login']));
        }

        $user_id = $_POST['user_id'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        $verified_users = $this->core->getQueries()->getUserIdEmailExists($email, $user_id);
        $unverified_users = $this->core->getQueries()->getUnverifiedUserIdEmailExists($email, $user_id);

        if (in_array($email, array_column($unverified_users, 'user_email'), true) || in_array($email, array_column($verified_users, 'user_email'), true)) {
            $this->core->addErrorMessage('Email already exists.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'email_verification']));
        }

        if (in_array($user_id, array_column($verified_users, 'user_id'), true)) {
            $this->core->addErrorMessage('User ID already exists');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }

        if (!$this->isGoodPassword($password)) {
            $this->core->addErrorMessage('Password does not meet the requirements.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }

        if ($password !== $confirm_password) {
            $this->core->addErrorMessage('Passwords did not match.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }

        if (!$this->isAcceptedEmail($email)) {
            $this->core->addErrorMessage('This email is not accepted.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }

        if (!$this->isAcceptedUserId($user_id, $_POST['given_name'], $_POST['family_name'], $email)) {
            $this->core->addErrorMessage('This user id does not meet requirements.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }
        $verification_values = $this->generateVerificationCode();
        $user = new User($this->core, [
            'user_id' => $user_id,
            'user_givenname' => $_POST['given_name'],
            'user_familyname' => $_POST['family_name'],
            'user_password' => $password,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => $email,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_verification_code' => $verification_values['code'],
            'user_verification_expiration' => $verification_values['exp']
        ]);

        try {
            $this->core->getQueries()->insertUnverifiedSubmittyUser($user);
            $this->sendVerificationEmail($email, $verification_values['code'], $user_id);
            $this->core->addSuccessMessage('Verification Email Sent');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'email_verification']));
        }
        catch (\Error $e) {
            Logger::error($e);
            $this->core->addErrorMessage('Failed to create the account.');
            return new RedirectResponse($this->core->buildUrl(['authentication', 'create_account']));
        }
    }
}
