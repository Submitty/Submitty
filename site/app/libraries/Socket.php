<?php

    namespace app\libraries;

    use PHPUnit\Framework\ExpectationFailedException;
    use Ratchet\MessageComponentInterface;
    use Ratchet\ConnectionInterface;
    use app\libraries\database;

    class Socket implements MessageComponentInterface {
        protected $clients;
        protected $sessions;
        protected $users;
        protected $core;

        public function __construct() {
            $this->clients = new \SplObjectStorage;
            $this->sessions = [];

            $this->core = new Core();

            $this->core->loadMasterConfig();
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->core->loadMasterDatabase();
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->core->loadAuthentication();
            $this->core->loadCourseDatabase();

        }

        private function checkAuth($conn){
            $request = $conn->httpRequest;
            $client_id = $conn->resourceId;
            $userAgent = $request->getHeader('user-agent');

            if( $userAgent[0] == "websocket-client-php"){
                return true;
            }
            else{
                $cookieString = $request->getHeader("cookie");
                parse_str(strtr($cookieString[0], array('&' => '%26', '+' => '%2B', ';' => '&')), $cookies);

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
                        $conn->send('{"sys": "Unauthenticated User"}');
                        $conn->close();
                        return false;
                    }
                    else {
                        $this->setSocketClient($user_id, $conn);
                        $conn->send('{"sys": "Connected"}');
                        return true;
                    }
                }
                catch (\InvalidArgumentException $exc) {
                    die($exc);
                }
            }
        }

        private function broadcast($from, $content, $all = true){
            if($all) {
                foreach ($this->clients as $client) {
                    $client->send($content);
                }
            }
            else{
                foreach ($this->clients as $client) {
                    if($client != $from){
                        $client->send($content);
                    }
                }
            }
        }

        /** Note: Even if some of the below functions are not currently used,
         * Do not remove them. Use them in the future for features like -
         *      1. pushing notifications to a set of users
         *      2. User to User (s) chat/communication
         */

        /**
         * Fetches Connection of a given User
         * @param $user_id
         * @return array
         */
        private function getSocketClientID($user_id){
            if(isset($this->users[$user_id])) {
                return $this->users[$user_id];
            }
            else
            {
                return false;
            }
        }

        /**
         * Fetches User ID of a given socket Connection
         * @param $conn
         * @return integer
         */
        private function getSocketUserID($conn){
            if(isset($this->sessions[$conn->resourceId])) {
                return $this->sessions[$conn->resourceId];
            }
            else
            {
                return false;
            }
        }

        /**
         * Sets Connection associativity with User
         * @param $user_id
         * @param $conn
         * @return void
         */
        private function setSocketClient($user_id, $conn){
            $this->sessions[$conn->resourceId] = $user_id;
            $this->users[$user_id] = $conn;
        }

        /**
         * Deletes Connection associativity with User
         * @param $conn
         * @return void
         */
        private function removeSocketClient($conn){
            $user_id = $this->getSocketUserID($conn);
            unset($this->sessions[$conn->resourceId]);
            unset($this->users[$user_id]);
        }

        public function onOpen(ConnectionInterface $conn) {
            $this->clients->attach($conn);

            if($this->checkAuth($conn)){
                echo "New connection! ({$conn->resourceId})}\n";
            }
        }

        public function onMessage(ConnectionInterface $from, $msgString) {
            if($this->checkAuth($from)){
                $msg = json_decode($msgString, true);

                switch ($msg["type"]){
                    case "new_thread":
                    case "new_post":
                        $user_id = $msg["data"]["user_id"];
                        if($fromConn = $this->getSocketClientID($user_id)){
                            $this->broadcast($fromConn, $msgString, false);
                        }else {
                            $this->broadcast($from, $msgString, false);
                        }
                        break;
                    default: $this->broadcast($from, $msgString, true);
                }
            }
        }

        public function onClose(ConnectionInterface $conn) {
            $this->removeSocketClient($conn);
            $this->clients->detach($conn);
            $conn->send('{"sys": "Disconnected"}');
            echo "Connection {$conn->resourceId} has disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
            echo "An error has occurred: {$e->getMessage()}\n";

            $conn->close();
        }
    }