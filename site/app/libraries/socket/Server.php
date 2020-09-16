<?php

declare(strict_types=1);

namespace app\libraries\socket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use app\libraries\Core;
use app\libraries\TokenManager;

class Server implements MessageComponentInterface {

    // Holds the mapping between pages that have open socket clients and those clients
    private $clients;

    // Holds the mapping between Connection Objects IDs (key) and user current course&page (value)
    private $pages;

    // Holds the mapping between Connection Objects IDs (key) and User_ID (value)
    private $sessions;

    // Holds the mapping between User_ID (key) and Connection objects (value)
    private $users;

    /** @var Core */
    private $core;

    public function __construct(Core $core) {
        $this->clients = [];
        $this->pages = [];
        $this->sessions = [];
        $this->users = [];
        $this->core = $core;
    }

    /**
     * This function checks if a given connection object is authenticated
     * It uses the submitty_session cookie in the header data to work
     * @param ConnectionInterface $conn
     * @return bool
     */
    private function checkAuth(ConnectionInterface $conn): bool {
        $request = $conn->httpRequest;
        $user_agent = $request->getHeader('User-Agent')[0];

        if ($user_agent === 'websocket-client-php') {
            $session_secret = $request->getHeader('Session-Secret')[0];
            if ($session_secret  === $this->core->getConfig()->getSecretSession()) {
                return true;
            }
            return false;
        }

        $cookieString = $request->getHeader("cookie")[0];
        parse_str(strtr($cookieString, ['&' => '%26', '+' => '%2B', ';' => '&']), $cookies);
        $sessid = $cookies['submitty_session'];

        try {
            $token = TokenManager::parseSessionToken(
                $sessid,
                $this->core->getConfig()->getBaseUrl(),
                $this->core->getConfig()->getSecretSession()
            );
            $session_id = $token->getClaim('session_id');
            $user_id = $token->getClaim('sub');
            $logged_in = $this->core->getSession($session_id, $user_id);
            if (!$logged_in) {
                return false;
            }
            else {
                $this->setSocketClient($user_id, $conn);
                return true;
            }
        }
        catch (\InvalidArgumentException $exc) {
            die($exc);
        }
    }

    /**
     * Push a given message to all-but-sender connections on the same course&page
     * @param ConnectionInterface $from
     * @param string $content
     * @param string $page_name
     * @return void
     */
    private function broadcast(ConnectionInterface $from, string $content, string $page_name): void {
        foreach ($this->clients[$page_name] as $client) {
            if ($client !== $from) {
                $client->send($content);
            }
        }
    }

    /**
     * Connection object is the object stored against $this->clients for a given connection
     * Connection object contains all details of the connection and can be used to push messages to client
     *
     * User_ID refers to the user_id of the user who is making a connection to the socket from a web page
     * For example: if instructor has opened a socket connection from Forum threads page, user_id is instructor
     */

    /**
     * Fetches Connection object/s of a given User_ID
     * @param string $user_id
     * @return bool|ConnectionInterface
     */
    private function getSocketClients(string $user_id) {
        if (isset($this->users[$user_id])) {
            return $this->users[$user_id];
        }
        else {
            return false;
        }
    }

    /**
     * Fetches User_ID of a given socket Connection object
     * @param ConnectionInterface $conn
     * @return string|false
     */
    private function getSocketUserID(ConnectionInterface $conn) {
        if (isset($this->sessions[$conn->resourceId])) {
            return $this->sessions[$conn->resourceId];
        }
        else {
            return false;
        }
    }

    /**
     * Sets Connection object associativity with User_ID
     * @param string $user_id
     * @param ConnectionInterface $conn
     * @return void
     */
    private function setSocketClient(string $user_id, ConnectionInterface $conn): void {
        $this->sessions[$conn->resourceId] = $user_id;
        if (array_key_exists($user_id, $this->users)) {
            $this->users[$user_id][] = $conn;
        }
        else {
            $this->users[$user_id] = [$conn];
        }
    }

    /**
     * Deletes Connection object associativity with User_ID
     * @param ConnectionInterface $conn
     * @return void
     */
    private function removeSocketClient(ConnectionInterface $conn): void {
        $user_id = $this->getSocketUserID($conn);
        if ($user_id) {
            unset($this->sessions[$conn->resourceId]);
            foreach ($this->users[$user_id] as $client) {
                if ($client === $conn) {
                    unset($client);
                    break;
                }
            }
            unset($this->users[$user_id]);
            unset($this->pages[$conn->resourceId]);
        }
    }

    /**
     * Sets Connection object associativity with user course
     * @param string $page_name
     * @param ConnectionInterface $conn
     * @return void
     */
    private function setSocketClientPage(string $page_name, ConnectionInterface $conn): void {
        $this->pages[$conn->resourceId] = $page_name;
    }

    /**
     * Fetches User_ID of a given socket Connection object
     * @param ConnectionInterface $conn
     * @return string|false
     */
    private function getSocketClientPage(ConnectionInterface $conn) {
        if (isset($this->pages[$conn->resourceId])) {
            return $this->pages[$conn->resourceId];
        }
        else {
            return false;
        }
    }

    /**
     * On connection, add socket to tracked clients, but we do not need
     * to check auth here as that is done on every message.
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        if (!$this->checkAuth($conn)) {
            $conn->close();
        }
    }

    /**
     * Function to run whenever the socket receives a new message from any client
     * @param ConnectionInterface $from
     * @param string $msgString
     */
    public function onMessage(ConnectionInterface $from, $msgString) {
        if ($msgString === 'ping') {
            $from->send('pong');
            return;
        }

        $msg = json_decode($msgString, true);

        if ($msg["type"] === "new_connection") {
            if (isset($msg['page'])) {
                if (!array_key_exists($msg['page'], $this->clients)) {
                    $this->clients[$msg['page']] = new \SplObjectStorage();
                }
                $this->clients[$msg['page']]->attach($from);
                $this->setSocketClientPage($msg['page'], $from);

                if ($this->core->getConfig()->isDebug()) {
                    $course_page = explode('-', $this->getSocketClientPage($from));
                    echo "New connection --> user_id: '" . $this->getSocketUserID($from) . "' - term: '" . $course_page[0] . "' - course: '" . $course_page[1] . "' - page: '" . $course_page[2] . "'\n";
                }
            }
            else {
                $from->close();
            }
        }
        elseif (isset($msg['user_id'])) {
            // user_id is only sent with socket clients open from a php user_agent
            $user_open_clients = $this->getSocketClients($msg['user_id']);
            if (is_array($user_open_clients)) {
                foreach ($user_open_clients as $original_client) {
                    if ($this->getSocketClientPage($original_client) === $msg['page']) {
                        $original_client->close();
                        break;
                    }
                }
            }
            unset($msg['user_id']);
            $new_msg_string = json_encode($msg);
            $this->broadcast($from, $new_msg_string, $msg['page']);
            $from->close();
        }
        else {
            $this->broadcast($from, $msgString, $this->getSocketClientPage($from));
        }
    }

    /**
     * When any client closes the connection, remove information about them
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn): void {
        $user_current_page = $this->getSocketClientPage($conn);
        if ($user_current_page) {
            $this->clients[$user_current_page]->detach($conn);
        }
        $this->removeSocketClient($conn);
    }

    /**
     * When any error occurs within the socket server script
     * @param ConnectionInterface $conn
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void {
        $conn->close();
    }
}
