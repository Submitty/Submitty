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

        public function onOpen(ConnectionInterface $conn) {
            // Store the new connection to send messages to later
            $this->clients->attach($conn);

            $request = $conn->httpRequest;

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
                $expire_time = $token->getClaim('expire_time');
                $logged_in = $this->core->getSession($session_id, $token->getClaim('sub'));
                if (!$logged_in) {
                    $conn->send('{"sys": "Unauthenticated User"}');
                    $conn->close();
                }
                else {
                    $this->sessions[$conn->resourceId] = ["userid" => $token->getClaim('sub')];
                    $conn->send('{"sys": "Connected"}');
                }
            }
            catch (\InvalidArgumentException $exc) {
                echo "Error";
            }

            echo "New connection! ({$conn->resourceId}) from {$this->sessions[$conn->resourceId]["userid"]}\n";
        }

        public function onMessage(ConnectionInterface $from, $msgString) {
            $numRecv = count($this->clients) - 1;
            echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
                , $from->resourceId, $msgString, $numRecv, $numRecv == 1 ? '' : 's');

            $msg = json_decode($msgString, true);

            foreach ($this->clients as $client) {
//                if ($from !== $client) {
                    // The sender is not the receiver, send to each client connected
                    $client->send($msgString);
//                }
            }
        }

        public function onClose(ConnectionInterface $conn) {
            // The connection is closed, remove it, as we can no longer send it messages
            $this->clients->detach($conn);

            echo "Connection {$conn->resourceId} has disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
            echo "An error has occurred: {$e->getMessage()}\n";

            $conn->close();
        }
    }