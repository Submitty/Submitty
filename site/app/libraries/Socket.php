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
                        $this->setSocketClient($user_id, $client_id);
                        $conn->send('{"sys": "Connected"}');
                        return true;
                    }
                }
                catch (\InvalidArgumentException $exc) {
                    die($exc);
                }
            }
        }

        private function broadcast($content){
            foreach ($this->clients as $client) {
                $client->send($content);
            }
        }

        /**
         * Fetches Client ID (s) of a given User
         * @param $user_id
         * @return array
         */
        private function getSocketClientID($user_id){
            return $this->users[$user_id];
        }

        /**
         * Fetches User ID of a given socket client ID
         * @param $client_id
         * @return integer
         */
        private function getSocketUserID($client_id){
            if(isset($this->sessions[$client_id])) {
                return $this->sessions[$client_id];
            }
            else
            {
                return false;
            }
        }

        /**
         * Sets Client ID associativity with User
         * @param $user_id
         * @param $client_id
         * @return void
         */
        private function setSocketClient($user_id, $client_id){
            $this->sessions[$client_id] = $user_id;
            $this->users[$user_id] = $client_id;
        }

        /**
         * Deletes Client ID associativity with User
         * @param $client_id
         * @return void
         */
        private function removeSocketClient($client_id){
            $user_id = $this->sessions[$client_id];
            unset($this->sessions[$client_id]);
            unset($this->users[$user_id]);
        }

        public function onOpen(ConnectionInterface $conn) {
            $this->clients->attach($conn);

            if($this->checkAuth($conn)){
                echo "New connection! ({$conn->resourceId})}\n";
            }
        }

        public function onMessage(ConnectionInterface $from, $msgString) {
            $numRecv = count($this->clients) - 1;
            echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
                , $from->resourceId, $msgString, $numRecv, $numRecv == 1 ? '' : 's');

            if($this->checkAuth($from)){
                $msg = json_decode($msgString, true);

                switch ($msg["type"]){
                    default: $this->broadcast($msgString);
                }

                echo $msgString;
            }
        }

        public function onClose(ConnectionInterface $conn) {
            $this->removeSocketClient($conn->resourceId);
            $this->clients->detach($conn);
            $conn->send('{"sys": "Disconnected"}');
            echo "Connection {$conn->resourceId} has disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
            echo "An error has occurred: {$e->getMessage()}\n";

            $conn->close();
        }
    }