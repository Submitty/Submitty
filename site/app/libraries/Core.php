<?php

namespace app\libraries;

use app\authentication\AbstractAuthentication;
use app\exceptions\AuthenticationException;
use app\exceptions\CurlException;
use app\libraries\database\DatabaseFactory;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\DatabaseQueries;
use app\models\Config;
use app\models\forum\Forum;
use app\models\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Core
 *
 * This is the core of the application that contains references to the other main
 * libraries (such as Database, Session, etc.) that the application relies on.
 */
class Core {
    /**
     * @var \app\models\Config
     */
    private $config = null;

    /** @var AbstractDatabase */
    private $submitty_db = null;

    /** @var AbstractDatabase */
    private $course_db = null;

    /** @var AbstractAuthentication */
    private $authentication;

    /** @var SessionManager */
    private $session_manager;

    /** @var DatabaseFactory */
    private $database_factory;

    /** @var DatabaseQueries */
    private $database_queries;

    /** @var User */
    private $user = null;

    /** @var string */
    private $user_id = null;

    /** @var Output */
    private $output = null;

    /** @var GradingQueue */
    private $grading_queue = null;

    /** @var Access $access */
    private $access = null;

    /** @var Forum $forum */
    private $forum  = null;

    /** @var NotificationFactory */
    private $notification_factory;
    /** @var bool */
    private $redirect = true;

    /** @var bool */
    private $testing = false;


    /**
     * Core constructor.
     *
     * This sets up our core for usage, by starting up our Output class as well as any $_SESSION variables that we
     * need. This should be called first, then loadConfig() and then loadDatabases().
     */
    public function __construct() {
        $this->setOutput(new Output($this));
        $this->access = new Access($this);

        // initialize our alert queue if it doesn't exist
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }

        // initialize our alert types if one of them doesn't exist
        foreach (array('error', 'notice', 'success') as $key) {
            if (!isset($_SESSION['messages'][$key])) {
                $_SESSION['messages'][$key] = array();
            }
        }

        // we cast each of our controller markers to lower to normalize our controller switches
        // and prevent any unexpected page failures for users in entering a capitalized controller
        foreach (array('component', 'page', 'action') as $key) {
            $_REQUEST[$key] = (isset($_REQUEST[$key])) ? strtolower($_REQUEST[$key]) : "";
        }
        $this->notification_factory = new NotificationFactory($this);
    }

    /**
     * Disable all redirects for API calls.
     */
    public function disableRedirects() {
        $this->redirect = false;
    }

    /**
     * Load the config details for the application. This takes in a file from the ../../../config as well as
     * then a config.json contained in {$SUBMITTY_DATA_DIR}/courses/{$SEMESTER}/{$COURSE}/config directory. These
     * files contain details about how the database, location of files, late days settings, etc.
     *
     * Config model will throw exceptions if we cannot find a given $semester or $course on the filesystem.
     *
     * @param $semester
     * @param $course
     * @throws \Exception
     */
    public function loadCourseConfig($semester, $course) {
        if ($this->config === null) {
            throw new \Exception("Master config has not been loaded");
        }
        if (!empty($semester) && !empty($course)) {
            $course_path = FileUtils::joinPaths($this->config->getSubmittyPath(), "courses", $semester, $course);
            $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
            if (file_exists($course_json_path) && is_readable($course_json_path)) {
                $this->config->loadCourseJson($semester, $course, $course_json_path);
            }
            else {
                $message = "Unable to access configuration file " . $course_json_path . " for " .
                  $semester . " " . $course . " please contact your system administrator.\n" .
                  "If this is a new course, the error might be solved by restarting php-fpm:\n" .
                  "sudo service php7.2-fpm restart";
                $this->addErrorMessage($message);
            }
        }
    }

    public function loadMasterConfig() {
        $conf_path = FileUtils::joinPaths(__DIR__, '..', '..', '..', 'config');

        $this->setConfig(new Config($this));
        $this->config->loadMasterConfigs($conf_path);
    }

    public function setConfig(Config $config): void {
        $this->config = $config;
    }

    public function loadAuthentication() {
        $auth_class = "\\app\\authentication\\" . $this->config->getAuthentication();
        if (!is_subclass_of($auth_class, 'app\authentication\AbstractAuthentication')) {
            throw new \Exception("Invalid module specified for Authentication. All modules should implement the AbstractAuthentication interface.");
        }
        $this->authentication = new $auth_class($this);
        $this->setSessionManager(new SessionManager($this));
    }

    public function setSessionManager(SessionManager $manager) {
        $this->session_manager = $manager;
    }

    /**
     * Create a connection to the database using the details loaded from the config files. Additionally, we make
     * available queries that all parts of the application should go through. It should never be allowed to directly
     * go through the database as we risk ending up with the same queries repeated around the application which makes
     * changing and fixing bugs that much harder.
     *
     * @throws \Exception if we have not loaded the config yet
     */
    public function loadMasterDatabase() {
        if ($this->config === null) {
            throw new \Exception("Need to load the config before we can connect to the database");
        }

        $this->database_factory = new DatabaseFactory($this->config->getDatabaseDriver());

        $this->submitty_db = $this->database_factory->getDatabase($this->config->getSubmittyDatabaseParams());
        $this->submitty_db->connect();

        $this->setQueries($this->database_factory->getQueries($this));
    }

    public function loadCourseDatabase() {
        if ($this->config->isCourseLoaded()) {
            $this->course_db = $this->database_factory->getDatabase($this->config->getCourseDatabaseParams());
            $this->course_db->connect();

            $this->database_queries = $this->database_factory->getQueries($this);
        }
    }

    public function loadForum() {
        if ($this->config === null) {
            throw new \Exception("Need to load the config before we can create a forum instance.");
        }

        $this->forum = new Forum($this);
    }

    /**
     * Loads the shell of the grading queue
     *
     * @throws \Exception if we have not loaded the config yet
     */
    public function loadGradingQueue() {
        if ($this->config === null) {
            throw new \Exception("Need to load the config before we can initialize the grading queue");
        }

        $this->grading_queue = new GradingQueue(
            $this->config->getSemester(),
            $this->config->getCourse(),
            $this->config->getSubmittyPath()
        );
    }

    /**
     * Utility function that helps us load our models, especially when called from within a controller as then we
     * can mock this function to return a mock object instead of having to worry about dealing with setting up
     * the whole object.
     *
     * @param string $model
     * @param array ...$args
     *
     * @return object an instantiated instance of the given $model class using the AutoLoader.
     */
    public function loadModel($model, ...$args) {
        return new $model($this, ...$args);
    }

    /**
     * Deconstructor for the Core. Cleans up any messages from the server as well as disconnects
     * the database, running any open transactions that were left.
     */
    public function __destruct() {
        if ($this->course_db !== null) {
            $this->course_db->disconnect();
        }
        if ($this->submitty_db !== null) {
            $this->submitty_db->disconnect();
        }
    }

    public function addErrorMessage($message) {
        $_SESSION['messages']['error'][] = $message;
    }

    public function addNoticeMessage($message) {
        $_SESSION['messages']['notice'][] = $message;
    }

    public function addSuccessMessage($message) {
        $_SESSION['messages']['success'][] = $message;
    }

    /**
     * @return Config
     */
    public function getConfig(): ?Config {
        return $this->config;
    }

    /**
     * @return AbstractDatabase
     */
    public function getSubmittyDB() {
        return $this->submitty_db;
    }

    /**
     * @return AbstractDatabase
     */
    public function getCourseDB() {
        return $this->course_db;
    }

    public function setQueries(DatabaseQueries $queries): void {
        $this->database_queries = $queries;
    }

    /**
     * @return DatabaseQueries
     */
    public function getQueries() {
        return $this->database_queries;
    }

    /**
     * @return Forum
     */
    public function getForum() {
        return $this->forum;
    }

    public function loadUser(string $user_id) {
        // attempt to load rcs as both student and user
        $this->user_id = $user_id;
        $this->setUser($this->database_queries->getUserById($user_id));
    }

    public function setUser(User $user): void {
        $this->user = $user;
    }

    /**
     * Returns the user that the client is logged in as. Will return null if there is no user
     * to be logged in as.
     *
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Is a user loaded into the Core to be used for the client to be logged in as
     *
     * @return bool
     */
    public function userLoaded() {
        return $this->user !== null && $this->user->isLoaded();
    }

    /**
     * @return string
     */
    public function getCsrfToken() {
        return $this->session_manager->getCsrfToken();
    }

    /**
     * @return AbstractAuthentication
     */
    public function getAuthentication() {
        return $this->authentication;
    }

    /**
     * Given a session id (which should be coming from a cookie or request header), the database is queried to find
     * a session that matches the string, returning the user_id associated with it. The user_id is then checked to
     * make sure it matches our expected one from our session token, and if that all passes, then we load the
     * user into the core and returns true, else return false.
     *
     * @param string $session_id
     * @param string $expected_user_id
     *
     * @return bool
     */
    public function getSession(string $session_id, string $expected_user_id): bool {
        $user_id = $this->session_manager->getSession($session_id);
        if ($user_id === false || $user_id !== $expected_user_id) {
            return false;
        }
        $this->loadUser($user_id);
        return true;
    }

    /**
     * Remove the currently loaded session within the session manager, returning bool
     * on whether this was done or not
     *
     * @return bool
     */
    public function removeCurrentSession(): bool {
        return $this->session_manager->removeCurrentSession();
    }

    /**
     * Given an api_key (which should be coming from a parsed JWT), the database is queried to find
     * a user id that matches the api key, and let the core load the user.
     *
     * @param string $api_key
     *
     * @return bool
     */
    public function loadApiUser(string $api_key): bool {
        $user_id = $this->database_queries->getSubmittyUserByApiKey($api_key);
        if ($user_id === null) {
            return false;
        }
        $this->loadUser($user_id);
        return true;
    }

    /**
     * Authenticates the user against whatever method was choosen within the master.ini config file (and exists
     * within the app/authentication folder. The username and password for the user being authenticated are passed
     * in separately so that we do not worry about those being leaked via the stack trace that might get thrown
     * from this method. Returns True/False whether or not the authenication attempt succeeded/failed.
     *
     * @param bool $persistent_cookie should we store this for some amount of time (true) or till browser closure (false)
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function authenticate(bool $persistent_cookie = true): bool {
        $user_id = $this->authentication->getUserId();
        try {
            if ($this->authentication->authenticate()) {
                // Set the cookie to last for 7 days
                $token = TokenManager::generateSessionToken(
                    $this->session_manager->newSession($user_id),
                    $user_id,
                    $this->getConfig()->getBaseUrl(),
                    $this->getConfig()->getSecretSession(),
                    $persistent_cookie
                );
                return Utils::setCookie('submitty_session', (string) $token, $token->getClaim('expire_time'));
            }
        }
        catch (\Exception $e) {
            // We wrap all non AuthenticationExceptions so that they get specially processed in the
            // ExceptionHandler to remove password details
            if ($e instanceof AuthenticationException) {
                throw $e;
            }
            throw new AuthenticationException($e->getMessage(), $e->getCode(), $e);
        }
        return false;
    }

    /**
     * Authenticates the user against user's api key. Returns the json web token generated for the user.
     *
     * @return string | null
     *
     * @throws AuthenticationException
     */
    public function authenticateJwt() {
        $user_id = $this->authentication->getUserId();
        try {
            if ($this->authentication->authenticate()) {
                $this->database_queries->refreshUserApiKey($user_id);
                return (string) TokenManager::generateApiToken(
                    $this->database_queries->getSubmittyUserApiKey($user_id),
                    $this->getConfig()->getBaseUrl(),
                    $this->getConfig()->getSecretSession()
                );
            }
        }
        catch (\Exception $e) {
            // We wrap all non AuthenticationExceptions so that they get specially processed in the
            // ExceptionHandler to remove password details
            if ($e instanceof AuthenticationException) {
                throw $e;
            }
            throw new AuthenticationException($e->getMessage(), $e->getCode(), $e);
        }
        return null;
    }

    /**
     * Invalidates user's token by refreshing user's api key.
     *
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function invalidateJwt() {
        $user_id = $this->authentication->getUserId();
        try {
            if ($this->authentication->authenticate()) {
                $this->database_queries->refreshUserApiKey($user_id);
                return true;
            }
        }
        catch (\Exception $e) {
            // We wrap all non AuthenticationExceptions so that they get specially processed in the
            // ExceptionHandler to remove password details
            if ($e instanceof AuthenticationException) {
                throw $e;
            }
            throw new AuthenticationException($e->getMessage(), $e->getCode(), $e);
        }
        return false;
    }

    /**
     * Checks the inputted $csrf_token against the one that is loaded from the session table for the particular
     * signed in user.
     *
     * @param string $csrf_token
     *
     * @return bool
     */
    public function checkCsrfToken($csrf_token = null) {
        if ($csrf_token === null) {
            return isset($_POST['csrf_token']) && $this->getCsrfToken() === $_POST['csrf_token'];
        }
        else {
            return $this->getCsrfToken() === $csrf_token;
        }
    }

    /**
     * Given some URL parameters (parts), build a URL for the site using those parts.
     *
     * @param array  $parts
     *
     * @return string
     */
    public function buildUrl($parts = array()) {
        return $this->getConfig()->getBaseUrl() . implode("/", $parts);
    }

    /**
     * Given some URL parameters (parts), build a URL for the site using those parts.
     * This function will add the semester and course to the beginning of the new URL by default,
     * if you do not prepend this part (e.g. for authentication-related URLs), please set
     * $prepend_course_info to false.
     *
     * @param array  $parts
     *
     * @return string
     */
    public function buildCourseUrl($parts = array()) {
        array_unshift($parts, $this->getConfig()->getSemester(), $this->getConfig()->getCourse());
        return $this->buildUrl($parts);
    }

    /**
     * @param     $url
     * @param int $status_code
     */
    public function redirect($url, $http_response_code = 302) {
        if (!$this->redirect) {
            return;
        }
        header('Location: ' . $url, true, $http_response_code);
        if (!$this->testing) {
            die();
        }
    }

    /**
     * Returns all the different parts of the url used for choosing the appropriate controller
     * and method of that controller to run
     *
     * @return array
     */
    public function getControllerTypes() {
        return array('component', 'page', 'action');
    }

    /**
     * Returns a string that contains the course code as well as the course name only if the course name is not
     * blank, placing a colon between the two (if both are displayed)
     *
     * @return string
     */
    public function getFullCourseName() {
        $course_name = strtoupper($this->getConfig()->getCourse());
        if ($this->getConfig()->getCourseName() !== "") {
            $course_name .= ": " . htmlentities($this->getConfig()->getCourseName());
        }
        return $course_name;
    }

     /**
     * Returns either the 'actual' coursename or the coursename set by the professor.
     *
     * @return string
     */
    public function getDisplayedCourseName() {
        if ($this->getConfig()->getCourseName() !== "") {
            return htmlentities($this->getConfig()->getCourseName());
        }
        else {
            return $this->getConfig()->getCourse();
        }
    }

    public function getFullSemester() {
        $semester = $this->getConfig()->getSemester();
        if ($this->getConfig()->getSemester() !== "") {
            $arr1 = str_split($semester);
            $semester = "";
            if ($arr1[0] == "f") {
                $semester .= "Fall ";
            }
            elseif ($arr1[0] == "s") {
                $semester .= "Spring ";
            }
            elseif ($arr1[0] == "u") {
                $semester .= "Summer ";
            }

            $semester .= "20" . $arr1[1] . $arr1[2];
        }
        return $semester;
    }

    /**
     * @return Output
     */
    public function getOutput(): Output {
        return $this->output;
    }

    public function setOutput(Output $output): void {
        $this->output = $output;
    }

    /**
     * @return GradingQueue
     */
    public function getGradingQueue() {
        return $this->grading_queue;
    }

    /**
     * @return Access
     */
    public function getAccess() {
        return $this->access;
    }

    /**
     * Gets the current time in the config timezone
     * @return \DateTime
     */
    public function getDateTimeNow() {
        return new \DateTime('now', $this->getConfig()->getTimezone());
    }

    /**
     * Given a string URL, sets up a CURL request to that URL, wherein it'll either return the response
     * assuming that we
     *
     * @param string $url
     * @param mixed $data
     *
     * @return string
     *
     * @throws \app\exceptions\CurlException
     */
    public function curlRequest(string $url, $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($data)) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        try {
            $return = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (!curl_errno($ch) && $http_code === 200) {
                return $return;
            }
            throw new CurlException($ch, $return);
        }
        finally {
            curl_close($ch);
        }
    }

    /**
     * We use this function to allow us to bypass certain "safe" PHP functions that we cannot
     * bypass via mocking or some other method (like is_uploaded_file).
     * @return bool
     */
    public function isTesting(): bool {
        return $this->testing;
    }

    public function setTesting(bool $testing): void {
        $this->testing = $testing;
    }

    public function getNotificationFactory() {
        return $this->notification_factory;
    }

    /**
     * Check if we have a saved cookie with a session id and then that there exists
     * a session with that id. If there is no session, then we delete the cookie.
     * @return bool
     */
    public function isWebLoggedIn() {
        $logged_in = false;
        $cookie_key = 'submitty_session';
        if (isset($_COOKIE[$cookie_key])) {
            try {
                $token = TokenManager::parseSessionToken(
                    $_COOKIE[$cookie_key],
                    $this->getConfig()->getBaseUrl(),
                    $this->getConfig()->getSecretSession()
                );
                $session_id = $token->getClaim('session_id');
                $expire_time = $token->getClaim('expire_time');
                $logged_in = $this->getSession($session_id, $token->getClaim('sub'));
                // make sure that the session exists and it's for the user they're claiming
                // to be
                if (!$logged_in) {
                    // delete cookie that's stale
                    Utils::setCookie($cookie_key, "", time() - 3600);
                }
                else {
                    if ($expire_time > 0) {
                        Utils::setCookie(
                            $cookie_key,
                            (string) TokenManager::generateSessionToken(
                                $session_id,
                                $token->getClaim('sub'),
                                $this->getConfig()->getBaseUrl(),
                                $this->getConfig()->getSecretSession()
                            ),
                            $expire_time
                        );
                    }
                }
            }
            catch (\InvalidArgumentException $exc) {
                // Invalid cookie data, delete it
                Utils::setCookie($cookie_key, "", time() - 3600);
            }
        }
        return $logged_in;
    }

    /**
     * Check if the user has a valid jwt in the header.
     *
     * @param Request $request
     * @return bool
     */
    public function isApiLoggedIn(Request $request) {
        $logged_in = false;
        $jwt = $request->headers->get("authorization");
        if (!empty($jwt)) {
            try {
                $token = TokenManager::parseApiToken(
                    $request->headers->get("authorization"),
                    $this->getConfig()->getBaseUrl(),
                    $this->getConfig()->getSecretSession()
                );
                $api_key = $token->getClaim('api_key');
                $logged_in = $this->loadApiUser($api_key);
            }
            catch (\InvalidArgumentException $exc) {
                return false;
            }
        }

        return $logged_in;
    }
}
