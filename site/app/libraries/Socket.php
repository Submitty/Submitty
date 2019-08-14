<?php

    namespace app\libraries;

    use Ratchet\MessageComponentInterface;
    use Ratchet\ConnectionInterface;

    use app\exceptions\BaseException;
    use app\libraries\Core;
    use app\libraries\ExceptionHandler;
    use app\libraries\Logger;
    use app\libraries\Utils;
    use app\libraries\routers\WebRouter;
    use app\libraries\response\Response;

    use Doctrine\Common\Annotations\AnnotationRegistry;
    use Symfony\Component\HttpFoundation\Request;

//    $loader = require_once(__DIR__.'/../vendor/autoload.php');
//    AnnotationRegistry::registerLoader([$loader, 'loadClass']);

    class Socket implements MessageComponentInterface {
        protected $clients;
        protected $sessions;

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

        }

        private function checkAuth($conn){
            $request = $conn->httpRequest;
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
                    $logged_in = $this->core->getSession($session_id, $token->getClaim('sub'));
                    if (!$logged_in) {
                        $conn->send('{"sys": "Unauthenticated User"}');
                        $conn->close();
                        return false;
                    }
                    else {
                        $this->sessions[$conn->resourceId] = ["userid" => $token->getClaim('sub')];
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
            $this->clients->detach($conn);
            $conn->send('{"sys": "Disconnected"}');
            echo "Connection {$conn->resourceId} has disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
            echo "An error has occurred: {$e->getMessage()}\n";

            $conn->close();
        }
    }