<?php

declare(strict_types=1);

namespace app\libraries\socket;

use app\exceptions\DatabaseException;
use app\libraries\FileUtils;
use app\libraries\Utils;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use app\libraries\Core;
use app\libraries\TokenManager;
use Psr\Http\Message\RequestInterface;
use app\entities\poll\Poll;

class Server implements MessageComponentInterface {
    // Holds the mapping between pages that have open socket clients and those clients
    /** @var array<string, \SplObjectStorage> */
    private $clients = [];

    // Holds the mapping between Connection Objects IDs (key) and user current course&page (value)
    /** @var array */
    private $pages = [];

    // Holds the mapping between Connection Objects IDs (key) and User_ID (value)
    /** @var array */
    private $sessions = [];

    // Holds the set of PHPWebSocket clients that are currently connected
    /** @var array<int, bool> */
    private $php_websocket_clients = [];

    /** @var Core */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    private function log(string $message) {
        if ($this->core->getConfig()->isDebug()) {
            echo $message . "\n";
        }
    }

    private function logError(\Throwable $error, ConnectionInterface $conn) {
        $page = $this->pages[$conn->resourceId] ?? "null";

        $date = getdate(time());
        $timestamp = Utils::pad($date['hours']) . ":" . Utils::pad($date['minutes']) . ":" . Utils::pad($date['seconds']);
        $timestamp .= " ";
        $timestamp .= Utils::pad($date['mon']) . "/" . Utils::pad($date['mday']) . "/" . $date['year'];

        $message  = $timestamp . "\n";
        $message .= "Message:\n";
        $message .= $error->getMessage() . "\n\n";
        $message .= "Stack Trace:\n";
        $message .= $error->getTraceAsString() . "\n\n";
        $message .= "Page: " . $page . "\n";
        $message .= str_repeat("=-", 30) . "=" . "\n";

        $filename = $date['year'] . Utils::pad($date['mon']) . Utils::pad($date['mday']);
        file_put_contents(
            FileUtils::joinPaths($this->core->getConfig()->getLogPath(), "socket_errors", "{$filename}.log"),
            $message,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * This function checks if a given connection is the PHP WebSocket client
     * @param ConnectionInterface $conn
     * @return bool
     */
    private function isWebSocketClient(ConnectionInterface $conn): bool {
        $headers = $conn->httpRequest->getHeaders();
        $user_agent = $headers['User-Agent'][0] ?? '';
        $session_secret = $headers['Session-Secret'][0] ?? '';

        return $user_agent === 'websocket-client-php' && $session_secret === $this->core->getConfig()->getSecretSession();
    }

    /**
     * This function checks if a given connection object is authenticated
     * It uses the submitty_session cookie in the header data to work
     * @param ConnectionInterface $conn
     * @return bool
     */
    private function checkAuth(ConnectionInterface $conn): bool {
        // The httpRequest property does exist on connections...
        $request = $conn->httpRequest;

        if (!$request instanceof RequestInterface) {
            return false;
        }

        if ($this->isWebSocketClient($conn)) {
            $this->php_websocket_clients[$conn->resourceId] = true;
            $this->log("New connection {$conn->resourceId} --> websocket-client-php");
            return true;
        }

        $cookieString = $request->getHeader("cookie")[0] ?? '';
        parse_str(strtr($cookieString, ['&' => '%26', '+' => '%2B', ';' => '&']), $cookies);
        if (empty($cookies['submitty_session'])) {
            return false;
        }
        $sessid = $cookies['submitty_session'];

        try {
            $query_params = [];
            $query = $request->getUri()->getQuery();
            parse_str($query, $query_params);
            if (!isset($query_params['page']) || !isset($query_params['course']) || !isset($query_params['term'])) {
                return false;
            }
            $page = $query_params['page'];
            $term = $query_params['term'];
            $course = $query_params['course'];
            $this->core->loadCourseConfig($term, $course);
            $this->core->loadCourseDatabase();
            $token = TokenManager::parseSessionToken($sessid);
            $session_id = $token->claims()->get('session_id');
            $user_id = $token->claims()->get('sub');
            if (!$this->core->getSession($session_id, $user_id)) {
                return false;
            }
            if (!$this->core->getAccess()->canI("course.view", ['course' => $course, 'semester' => $term])) {
                return false;
            }
            switch ($page) {
                case 'discussion_forum':
                case 'office_hours_queue':
                    break;
                case 'chatrooms':
                    if (isset($query_params['chatroom_id'])) {
                        $page = $page . '-' . $query_params['chatroom_id'];
                    }
                    break;
                case 'polls':
                    if (!isset($query_params['poll_id']) || !isset($query_params['instructor'])) {
                        return false;
                    }
                    $instructor = filter_var($query_params['instructor'], FILTER_VALIDATE_BOOLEAN);
                    $poll = $this->core->getCourseEntityManager()->getRepository(Poll::class)->findByIDWithOptions(intval($query_params['poll_id']));
                    if (!$this->core->getAccess()->canI("poll.view", ['poll' => $poll])) {
                        return false;
                    }
                    else if ($instructor && !$this->core->getAccess()->canI("poll.view.histogram", ['poll' => $poll])) {
                        return false;
                    }
                    $page = $page . '-' . $query_params['poll_id'] . '-' . ($instructor ? 'instructor' : 'student');
                    break;
                case 'grade_inquiry':
                    if (!isset($query_params['gradeable_id'])) {
                        return false;
                    }
                    $gradeable = $this->core->getQueries()->getGradeableConfig($query_params['gradeable_id']);
                    $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $query_params['submitter_id']);
                    if (!$this->core->getAccess()->canI("grading.electronic.grade_inquiry", ['gradeable' => $gradeable, 'graded_gradeable' => $graded_gradeable])) {
                        return false;
                    }
                    $page = $page . '-' . $query_params['gradeable_id'] . '_' . $query_params['submitter_id'];
                    break;
                case 'grading':
                    if (!isset($query_params['gradeable_id'])) {
                        return false;
                    }
                    $gradeable = $this->core->getQueries()->getGradeableConfig($query_params['gradeable_id']);
                    if (!$this->core->getAccess()->canI("grading.simple.grade", ['gradeable' => $gradeable])) {
                        return false;
                    }
                    $page = $page . '-' . $query_params['gradeable_id'];
                    break;
                default:
                    return false;
            }
            $this->setSocketClient($user_id, $conn);
            $page = $term . "-" . $course . "-" . $page;
            if (!array_key_exists($page, $this->clients)) {
                $this->clients[$page] = new \SplObjectStorage();
            }
            $this->clients[$page]->attach($conn);
            $this->setSocketClientPage($page, $conn);
            $this->log("New connection {$conn->resourceId} --> user_id: '" . $user_id . "' - page: '" . $page . "'");
            return true;
        }
        catch (\InvalidArgumentException $exc) {
            $this->logError($exc, $conn);
            return false;
        }
    }

    /**
     * Push a given message to all-but-sender connections on the same course and page
     */
    private function broadcast(ConnectionInterface $from, string $content, string $page_name): void {
        if (!array_key_exists($page_name, $this->clients)) {
            return; // Ignore broadcast requests for pages without active connections
        }
        elseif (!isset($this->php_websocket_clients[$from->resourceId])) {
            return; // Ignore client-side broadcast requests
        }

        foreach ($this->clients[$page_name] as $client) {
            if ($client !== $from) {
                $client->send($content);
            }
        }
    }

    /**
     * Fetches User_ID of a given socket Connection object
     */
    private function getSocketUserID(ConnectionInterface $conn): ?string {
        return $this->sessions[$conn->resourceId] ?? null;
    }

    /**
     * Sets Connection object associativity with User_ID
     */
    private function setSocketClient(string $user_id, ConnectionInterface $conn): void {
        $this->sessions[$conn->resourceId] = $user_id;
    }

    /**
     * Sets Connection object associativity with user course
     */
    private function setSocketClientPage(string $page_name, ConnectionInterface $conn): void {
        $this->pages[$conn->resourceId] = $page_name;
    }

    /**
     * Fetches User_ID of a given socket Connection object
     */
    private function getSocketClientPage(ConnectionInterface $conn): ?string {
        return $this->pages[$conn->resourceId] ?? null;
    }

    /**
     * Check the authentication status of the connection when it gets opened
     */
    public function onOpen(ConnectionInterface $conn): void {
        try {
            if (!$this->checkAuth($conn)) {
                $conn->close();
            }
        }
        catch (DatabaseException $de) {
            try {
                $this->core->loadMasterDatabase();
                $this->logError($de, $conn);
                $this->onOpen($conn);
            }
            catch (\Exception $e) {
                $this->logError($de, $conn);
                $this->logError($e, $conn);
                $this->closeWithError($conn);
            }
        }
        catch (\Throwable $t) {
            $this->logError($t, $conn);
            $this->closeWithError($conn);
        }
    }

    /**
     * Function to run whenever the socket receives a new message from any client
     * @param ConnectionInterface $from
     * @param string $msgString
     */
    public function onMessage(ConnectionInterface $from, $msgString): void {
        try {
            if ($msgString === 'ping') {
                $from->send('pong');
                return;
            }

            $msg = json_decode($msgString, true);

            if (isset($msg['user_id']) && isset($msg['page']) && is_string($msg['page'])) {
                // user_id is always sent with socket clients open from a php user_agent
                unset($msg['user_id']);
                $new_msg_string = json_encode($msg);
                $this->broadcast($from, $new_msg_string, $msg['page']);
                $from->close();
            }
            else {
                $this->broadcast($from, $msgString, $this->getSocketClientPage($from));
            }
        }
        catch (\Throwable $t) {
            $this->logError($t, $from);
        }
    }

    /**
     * When any client closes the connection, remove information about them
     */
    public function onClose(ConnectionInterface $conn): void {
        $this->log("Closing connection {$conn->resourceId}");
        $user_current_page = $this->getSocketClientPage($conn);
        unset($this->php_websocket_clients[$conn->resourceId]);
        if ($user_current_page) {
            $this->clients[$user_current_page]->detach($conn);
            if ($this->clients[$user_current_page]->count() === 0) {
                unset($this->clients[$user_current_page]);
            }
            unset($this->pages[$conn->resourceId]);
        }
        $user_id = $this->getSocketUserID($conn);
        if ($user_id) {
            unset($this->sessions[$conn->resourceId]);
        }
    }

    public function closeWithError(ConnectionInterface $conn): void {
        $msg = ['error' => 'Server error'];
        $conn->send(json_encode($msg));
        $conn->close();
    }

    /**
     * When any error occurs within the socket server script
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void {
        $conn->close();
    }
}
