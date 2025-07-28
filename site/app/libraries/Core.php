<?php

namespace app\libraries;

use app\authentication\AbstractAuthentication;
use app\exceptions\AuthenticationException;
use app\exceptions\CurlException;
use app\libraries\database\DatabaseFactory;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\DatabaseQueries;
use app\libraries\TokenManager;
use app\models\Config;
use app\models\User;
use app\entities\Session;
use app\repositories\SessionRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\NullLogger;
use BrowscapPHP\Browscap;

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

    /** @var EntityManager */
    private $submitty_entity_manager;

    /** @var EntityManager */
    private $course_entity_manager;

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


    /** @var Output */
    private $output = null;

    /** @var GradingQueue */
    private $grading_queue = null;

    /** @var Access $access */
    private $access = null;

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
            $_SESSION['messages'] = [];
        }

        // initialize our alert types if one of them doesn't exist
        foreach (['error', 'notice', 'success'] as $key) {
            if (!isset($_SESSION['messages'][$key])) {
                $_SESSION['messages'][$key] = [];
            }
        }

        // we cast each of our controller markers to lower to normalize our controller switches
        // and prevent any unexpected page failures for users in entering a capitalized controller
        foreach (['component', 'page', 'action'] as $key) {
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
     * @param string $semester
     * @param string $course
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
                  "sudo service php" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "-fpm restart";
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

    private function createEntityManager(AbstractDatabase $database): EntityManager {
        $cache_path = FileUtils::joinPaths(dirname(__DIR__, 2), 'cache', 'doctrine');
        $cache = new PhpFilesAdapter("", 0, $cache_path);
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [FileUtils::joinPaths(__DIR__, '..', 'entities')],
            $this->config->isDebug(),
            FileUtils::joinPaths(dirname(__DIR__, 2), 'cache', 'doctrine-proxy'),
            $cache
        );

        return new EntityManager($database->getConnection(), $config);
    }

    /**
     * Create a connection to the database using the details loaded from the config files. Additionally, we make
     * available queries that all parts of the application should go through. It should never be allowed to directly
     * go through the database as we risk ending up with the same queries repeated around the application which makes
     * changing and fixing bugs that much harder.
     *
     * @throws \Exception if we have not loaded the config yet
     */
    public function loadMasterDatabase(): void {
        if ($this->config === null) {
            throw new \Exception("Need to load the config before we can connect to the database");
        }

        $this->database_factory = new DatabaseFactory($this->config->getDatabaseDriver());

        $this->submitty_db = $this->database_factory->getDatabase($this->config->getSubmittyDatabaseParams());
        $this->submitty_db->connect($this->config->isDebug());

        $this->setQueries($this->database_factory->getQueries($this));
        $this->submitty_entity_manager = $this->createEntityManager($this->submitty_db);
    }

    public function setMasterDatabase(AbstractDatabase $database): void {
        $this->submitty_db = $database;
    }

    public function getSubmittyEntityManager(): EntityManager {
        return $this->submitty_entity_manager;
    }

    public function getSubmittyQueries(): array {
        if (!$this->config->isDebug() || !$this->submitty_db) {
            return [];
        }
        return $this->submitty_db->getPrintQueries();
    }

    public function loadCourseDatabase(): void {
        if (!$this->config->isCourseLoaded()) {
            return;
        }
        if ($this->course_db !== null && $this->course_db->isConnected()) {
            $this->course_db->disconnect();
        }
        $this->course_db = $this->database_factory->getDatabase($this->config->getCourseDatabaseParams());
        $this->course_db->connect($this->config->isDebug());

        $this->setQueries($this->database_factory->getQueries($this));
        $this->course_entity_manager = $this->createEntityManager($this->course_db);
    }

    public function setCourseDatabase(AbstractDatabase $database): void {
        $this->course_db = $database;
    }

    public function setCourseEntityManager(EntityManager $entity_manager): void {
        $this->course_entity_manager = $entity_manager;
    }

    public function getCourseEntityManager(): EntityManager {
        return $this->course_entity_manager;
    }

    public function getCourseQueries(): array {
        if (!$this->config->isDebug() || !$this->course_db) {
            return [];
        }
        return $this->course_db->getPrintQueries();
    }

    public function hasDBPerformanceWarning(): bool {
        if (count($this->getSubmittyQueries()) + count($this->getCourseQueries()) > 20) {
            return true;
        }

        if (($this->course_db !== null && $this->course_db->hasDuplicateQueries()) || ($this->submitty_db !== null && $this->submitty_db->hasDuplicateQueries())) {
            return true;
        }

        return false;
    }

    private function logPerformanceWarning(): void {
        if (!$this->config->isDebug()) {
            return;  // We never want to log these warnings on production
        }

        $ignore_list_path = FileUtils::joinPaths($this->config->getSubmittyInstallPath(), 'site', '.performance_warning_ignore.json');
        $ignore_list = json_decode(file_get_contents($ignore_list_path));

        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        foreach ($ignore_list as $regex) {
            if ($this->getConfig()->getTerm() !== null) {
                $regex = str_replace("<term>", $this->getConfig()->getTerm(), $regex);
            }
            if ($this->getConfig()->getCourse() !== null) {
                $regex = str_replace("<course>", $this->getConfig()->getCourse(), $regex);
            }
            $regex = str_replace("<gradeable>", "[A-Za-z0-9\\-\\_]+", $regex);
            if (preg_match("#^" . $regex . "(\?.*)?$#", $_SERVER['REQUEST_URI']) === 1) {
                return; // this route matches an ignore rule
            }
        }

        // didn't match any of the ignore rules...print a warning
        $num_queries = count($this->getSubmittyQueries()) + count($this->getCourseQueries());
        Logger::debug("Excessive or duplicate queries observed: {$num_queries} queries executed.\nMethod: {$_SERVER['REQUEST_METHOD']}");
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
            $this->config->getTerm(),
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
        // If this is in debug mode and performance warnings were generated, log them before closing the DB connection
        if ($this->config !== null && $this->config->isDebug() && $this->hasDBPerformanceWarning()) {
            $this->logPerformanceWarning();
        }

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

    public function loadUser(string $user_id) {
        // attempt to load rcs as both student and user
        $this->setUser($this->database_queries->getUserById($user_id));
        $this->getOutput()->setTwigTimeZone($this->getUser()->getTimeZone());
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
     * Get the session id of the current session otherwise return false
     *
     * @return string|bool
     */
    public function getCurrentSessionId() {
        $session_id = $this->session_manager->getCurrentSessionId();
        if ($session_id) {
            return $session_id;
        }
        return false;
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
     * Initializes the token manager to be used. This should be called
     * after the config has been loaded.
     *
     * @return void
     */
    public function initializeTokenManager(): void {
        TokenManager::initialize($this->config->getSecretSession(), $this->config->getBaseUrl());
    }

    /**
     * Authenticates the user against whatever method was chosen within the master.ini config file (and exists
     * within the app/authentication folder. The username and password for the user being authenticated are passed
     * in separately so that we do not worry about those being leaked via the stack trace that might get thrown
     * from this method. Returns True/False whether or not the authentication attempt succeeded/failed.
     *
     * @param bool $persistent_cookie should we store this for some amount of time (true) or till browser closure (false)
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function authenticate(bool $persistent_cookie = true): bool {
        try {
            if ($this->authentication->authenticate()) {
                $user_id = $this->authentication->getUserId();
                // Get information about user's browser
                try {
                    $path = FileUtils::joinPaths(
                        $this->getConfig()->getSubmittyInstallPath(),
                        'site',
                        'vendor',
                        'browscap',
                        'browscap-php',
                        'resources'
                    );
                    $fs_adapter = new FilesystemAdapter("", 0, $path);
                    $cache = new Psr16Cache($fs_adapter);
                    $logger = new NullLogger();
                    $bc = new Browscap($cache, $logger);
                    $browser_info = $bc->getBrowser();
                    $browser_info = [
                        'browser' => $browser_info->browser,
                        'version' => $browser_info->version,
                        'platform' => $browser_info->platform,
                    ];
                }
                catch (\Exception $e) {
                    $browser_info = [
                        'browser' => 'Unknown',
                        'version' => '',
                        'platform' => 'Unknown',
                    ];
                }
                $new_session_id = $this->session_manager->newSession($user_id, $browser_info);
                if ($this->database_queries->getSingleSessionSetting($user_id)) {
                    /** @var SessionRepository $repo */
                    $repo = $this->getSubmittyEntityManager()->getRepository(Session::class);
                    $repo->removeUserSessionsExcept($user_id, $new_session_id);
                }
                // Set the cookie to last for 7 days
                $token = TokenManager::generateSessionToken(
                    $new_session_id,
                    $user_id,
                    $persistent_cookie
                );
                return Utils::setCookie('submitty_session', $token->toString(), $token->claims()->get('expire_time'));
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
                return TokenManager::generateApiToken(
                    $this->database_queries->getSubmittyUserApiKey($user_id)
                )->toString();
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
    public function buildUrl($parts = []) {
        return $this->getConfig()->getBaseUrl() . implode("/", $parts);
    }

    /**
     * Given some URL parameters (parts), build a URL for the site using those parts.
     * This function will add the semester and course to the beginning of the new URL.
     *
     * @param array  $parts
     * @return string
     */
    public function buildCourseUrl($parts = []) {
        array_unshift($parts, "courses", $this->getConfig()->getTerm(), $this->getConfig()->getCourse());
        return $this->buildUrl($parts);
    }

    /**
     * @param string $url
     * @param int $http_response_code
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
        return ['component', 'page', 'action'];
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
        $semester = $this->getConfig()->getTerm();
        if ($this->getConfig()->getTerm() !== "") {
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
     * Gets the time for the given string in the config timezone
     */
    public function getDateTimeSpecific(string $time_string): \DateTime {
        return new \DateTime($time_string, $this->getConfig()->getTimezone());
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

    public function getNotificationFactory(): NotificationFactory {
        return $this->notification_factory;
    }

    public function setNotificationFactory(NotificationFactory $factory) {
        $this->notification_factory = $factory;
    }

    /**
     * Check if we have a saved cookie with a session id and then that there exists
     * a session with that id. If there is no session, then we delete the cookie.
     * @return bool
     */
    public function isWebLoggedIn(): bool {
        $logged_in = false;
        $cookie_key = 'submitty_session';
        if (isset($_COOKIE[$cookie_key])) {
            try {
                $token = TokenManager::parseSessionToken(
                    $_COOKIE[$cookie_key]
                );
                $session_id = $token->claims()->get('session_id');
                $logged_in = $this->getSession($session_id, $token->claims()->get('sub'));
                // make sure that the session exists and it's for the user they're claiming to be
                if (!$logged_in) {
                    // delete cookie that's stale
                    Utils::setCookie($cookie_key, "", time() - 3600);
                }
                else {
                    // If more than a day has passed since we last updated the cookie, update it with the new timestamp
                    if ($this->session_manager->checkAndUpdateSession()) {
                        $new_token = TokenManager::generateSessionToken(
                            $session_id,
                            $token->claims()->get('sub')
                        );
                        Utils::setCookie(
                            $cookie_key,
                            $new_token->toString(),
                            $new_token->claims()->get('expire_time')
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
    public function isApiLoggedIn(Request $request): bool {
        $logged_in = false;
        $jwt = $request->headers->get("authorization");
        if (!empty($jwt)) {
            try {
                $token = TokenManager::parseApiToken(
                    $request->headers->get("authorization")
                );
                $api_key = $token->claims()->get('api_key');
                $logged_in = $this->loadApiUser($api_key);
            }
            catch (\InvalidArgumentException $exc) {
                return false;
            }
        }

        return $logged_in;
    }

    /**
     * Get the lang data for the current locale.
     *
     * @return array<mixed>|null
     */
    public function getLang(): array|null {
        if ($this->config !== null) {
            return $this->config->getLocale()->getLangData();
        }
        return null;
    }

    /**
     * Gets a list of supported locales.
     *
     * @return array<string>|null
     */
    public function getSupportedLocales() {
        if ($this->config !== null) {
            FileUtils::getDirContents(FileUtils::joinPaths($this->config->getSubmittyInstallPath(), "site", "cache", "lang"), $files);
            if (empty($files)) {
                return [];
            }
            $files = array_filter($files, fn(string $file): bool => str_ends_with($file, ".php"));
            $files = array_map(function (string $file) {
                $parts = explode(DIRECTORY_SEPARATOR, $file);
                return substr(end($parts), 0, -4);
            }, $files);
            return $files;
        }
        return null;
    }

    /**
     * Generate a websocket token for the current user with permissions for specified pages
     *
     * @param array<int, array<string, string>> $page_contexts Array of page contexts the user should have access to
     * @param int $expire_minutes Token expiration in minutes (default 30)
     * @return string JWT token string
     */
    public function generateWebsocketToken(array $page_contexts, int $expire_minutes = 30): string {
        if (!$this->userLoaded()) {
            throw new \BadMethodCallException("Cannot generate websocket token: no user loaded");
        }

        if (!$this->config->isCourseLoaded()) {
            throw new \BadMethodCallException("Cannot generate websocket token: no course loaded");
        }

        $authorized_pages = $this->access->getAuthorizedWebsocketPages(
            $this->user,
            $this->config->getTerm(),
            $this->config->getCourse(),
            $page_contexts
        );

        $token = TokenManager::generateWebsocketToken(
            $this->user->getId(),
            $authorized_pages,
            $expire_minutes
        );

        return $token->toString();
    }

    /**
     * Get or generate websocket token, similar to how session tokens are managed, using
     * existing valid tokens instead of always generating new ones
     *
     * @param array<int, array<string, string>> $page_contexts Array of page contexts the user should have access to
     * @param int $expire_minutes Token expiration in minutes (default 30)
     * @return string|null JWT token string or null if generation fails
     */
    public function getWebsocketToken(array $page_contexts, int $expire_minutes = 30): ?string {
        if (!$this->userLoaded() || !$this->config->isCourseLoaded()) {
            return null;
        }

        $cookie_key = 'submitty_websocket_token';
        $existing_token = $_COOKIE[$cookie_key] ?? null;

        // Check if we have an existing valid token
        if ($existing_token !== null) {
            try {
                $token = TokenManager::parseWebsocketToken($existing_token);
                $token_user_id = $token->claims()->get('sub');
                $token_pages = $token->claims()->get('authorized_pages');

                // Verify token is for current user
                if ($token_user_id === $this->user->getId()) {
                    // Calculate required pages for current context
                    $required_pages = $this->access->getAuthorizedWebsocketPages(
                        $this->user,
                        $this->config->getTerm(),
                        $this->config->getCourse(),
                        $page_contexts
                    );

                    // Check if existing token covers all required pages
                    $has_all_required = true;
                    foreach ($required_pages as $required_page) {
                        if (!in_array($required_page, $token_pages, true)) {
                            $has_all_required = false;
                            break;
                        }
                    }

                    // If token covers all required pages, reuse it
                    if ($has_all_required) {
                        return $existing_token;
                    }
                }
            }
            catch (\InvalidArgumentException $exc) {
                // Invalid or expired token, delete the cookie
                Utils::setCookie($cookie_key, "", time() - 3600);
            }
        }

        // Generate new token if no valid existing token
        try {
            $new_token = $this->generateWebsocketToken($page_contexts, $expire_minutes);

            // Store in cookie for reuse (shorter expiry than session tokens)
            $expire_time = time() + ($expire_minutes * 60);
            Utils::setCookie($cookie_key, $new_token, $expire_time);

            return $new_token;
        }
        catch (\Exception $e) {
            Logger::error("Failed to generate websocket token: " . $e->getMessage());
            return null;
        }
    }
}
