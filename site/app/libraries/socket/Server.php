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

class Server implements MessageComponentInterface {
    // Holds the mapping between pages that have open socket clients and those clients
    /** @var array */
    private $clients = [];

    // Holds the mapping between Connection Objects IDs (key) and user current course&page (value)
    /** @var array */
    private $pages = [];

    // Holds the mapping between Connection Objects IDs (key) and User_ID (value)
    /** @var array */
    private $sessions = [];

    // Holds the mapping between User_ID (key) and Connection objects (value)
    /** @var array  */
    private $users = [];

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
     * This function checks if a given connection object is authenticated
     * It uses the submitty_session cookie in the header data to work
     * @param ConnectionInterface $conn
     * @return bool
     */
    private function checkAuth(ConnectionInterface $conn): bool {
        // The httpRequest property does exist on connections...
        $request = $conn->httpRequest;
        $user_agent = $request->getHeader('User-Agent')[0] ?? '';

        if ($user_agent === 'websocket-client-php') {
            $this->log("New connection {$conn->resourceId} --> websocket-client-php");
            return $request->getHeader('Session-Secret')[0] === $this->core->getConfig()->getSecretSession();
        }

        $cookieString = $request->getHeader("cookie")[0] ?? '';
        parse_str(strtr($cookieString, ['&' => '%26', '+' => '%2B', ';' => '&']), $cookies);
        if (empty($cookies['submitty_session'])) {
            return false;
        }
        $sessid = $cookies['submitty_session'];

        try {
            $token = TokenManager::parseSessionToken($sessid);
            $session_id = $token->claims()->get('session_id');
            $user_id = $token->claims()->get('sub');
            $logged_in = $this->core->getSession($session_id, $user_id);
            if ($logged_in) {
                $this->setSocketClient($user_id, $conn);
            }
            return $logged_in;
        }
        catch (\InvalidArgumentException $exc) {
            return false;
        }
    }

    /**
     * Push a given message to all-but-sender connections on the same course and page
     */
    private function broadcast(string $from, string $content, string $page_name): void {
        foreach ($this->clients[$page_name] as $client) {
            if ($this->getSocketUserID($client) !== $from) {
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
        if (!isset($this->users[$user_id])) {
            $this->users[$user_id] = [];
        }
        $this->users[$user_id][] = $conn;
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

            $user_agent = $from->httpRequest->getHeader('User-Agent')[0] ?? '';
            $msg = json_decode($msgString, true);

            if (isset($msg["type"]) && $msg["type"] === "new_connection") {
                if (isset($msg['page']) && is_string($msg['page'])) {
                    if (!array_key_exists($msg['page'], $this->clients)) {
                        $this->clients[$msg['page']] = new \SplObjectStorage();
                    }
                    $this->clients[$msg['page']]->attach($from);
                    $this->setSocketClientPage($msg['page'], $from);

                    $course_page = explode('-', $this->getSocketClientPage($from));
                    $this->log("New connection {$from->resourceId} --> user_id: '" . $this->getSocketUserID($from) . "' - term: '" . $course_page[0] . "' - course: '" . $course_page[1] . "' - page: '" . $course_page[2]);
                }
                else {
                    $from->close();
                }
            }
            elseif (isset($msg['user_id']) && isset($msg['page']) && is_string($msg['page']) && $user_agent === 'websocket-client-php') {
                // Only broadcast Websocket messages strictly from the PHP client
                $sender = $msg['user_id'];
                unset($msg['user_id']);

                $new_msg_string = json_encode($msg);
                $this->broadcast($sender, $new_msg_string, $msg['page']);
                $from->close();
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
        if ($user_current_page) {
            $this->clients[$user_current_page]->detach($conn);
            unset($this->pages[$conn->resourceId]);
        }
        $user_id = $this->getSocketUserID($conn);
        if ($user_id) {
            unset($this->sessions[$conn->resourceId]);
            $this->users[$user_id] = array_filter($this->users[$user_id], function ($client) use ($conn) {
                return $client !== $conn;
            });
            if (empty($this->users[$user_id])) {
                unset($this->users[$user_id]);
            }
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
