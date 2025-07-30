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
     * This function checks if a given connection object is authenticated and authorized to access the requested page
     * using the websocket token provided in the query parameters, which is authorized by the web server.
     *
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

        // Parse query parameters
        $query_params = [];
        $query = $request->getUri()->getQuery();
        parse_str($query, $query_params);

        // Parse the authorization token from the query parameters
        $websocket_token = $query_params['ws_token'] ?? null;

        // If no token is provided, close the connection
        if ($websocket_token === null) {
            return false;
        }

        // If required parameters are not provided, close the connection
        if (!isset($query_params['page']) || !isset($query_params['course']) || !isset($query_params['term'])) {
            return false;
        }

        // Get the page from the query parameters
        $page = $query_params['page'];

        // Parse and validate the websocket token
        try {
            $token = TokenManager::parseWebsocketToken($websocket_token);
            $user_id = $token->claims()->get('sub');
            $session_id = $token->claims()->get('session_id');
            $authorized_pages = $token->claims()->get('authorized_pages');

            // Ensure the user is still logged in
            $logged_in = $this->core->getSession($session_id, $user_id);
            if (!$logged_in) {
                return false;
            }

            if ($page === 'discussion_forum' || $page === 'office_hours_queue' || ($page === 'chatroom' && !isset($query_params['chatroom_id']))) {
                // These pages are not stored in authorized_pages to avoid redundancy
                $key = 'defaults';
            }
            else {
                $key = $page;
            }

            // Build the page identifier for authorization checks
            $authorized_page = Utils::buildWebSocketPageIdentifier($key, $query_params);

            if ($authorized_page === null) {
                $this->log("Invalid page type '{$page}' for connection {$conn->resourceId}");
                return false;
            }
            elseif (!array_key_exists($authorized_page, $authorized_pages) || time() > intval($authorized_pages[$authorized_page])) {
                $this->log("Unauthorized page '{$authorized_page}' for connection {$conn->resourceId}");
                return false;
            }

            // Create the full page identifier for the connection
            $page_identifier = Utils::buildWebSocketPageIdentifier($page, $query_params);

            // Set up the connection
            $this->setSocketClient($user_id, $conn);
            if (!array_key_exists($page_identifier, $this->clients)) {
                $this->clients[$page_identifier] = new \SplObjectStorage();
            }
            $this->clients[$page_identifier]->attach($conn);
            $this->setSocketClientPage($page_identifier, $conn);

            $this->log("New connection {$conn->resourceId} --> user_id: '" . $user_id . "' - page: '" . $page_identifier . "'");
            return true;
        }
        catch (\InvalidArgumentException $exc) {
            $this->log("Token validation failed for connection {$conn->resourceId}: " . $exc->getMessage());
            $this->logError($exc, $conn);
            return false;
        }
        catch (\Exception $exc) {
            $this->log("Unexpected error during auth for connection {$conn->resourceId}: " . $exc->getMessage());
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
