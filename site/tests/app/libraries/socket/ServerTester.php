<?php

namespace tests\app\libraries\socket;

use app\libraries\socket\Server;
use app\libraries\TokenManager;
use GuzzleHttp\Psr7\Request;
use Ratchet\ConnectionInterface;
use tests\BaseUnitTest;

class ServerTester extends BaseUnitTest {
    private function connection(int $id, string $page, array $headers): ConnectionInterface {
        return new class ($id, $page, $headers) implements ConnectionInterface {
            public int $resourceId;
            public Request $httpRequest;
            public array $messages = [];
            public bool $closed = false;

            public function __construct(int $id, string $page, array $headers) {
                $this->resourceId = $id;
                $this->httpRequest = new Request('GET', 'http://localhost/ws?term=s26&course=sample&page=' . $page, $headers);
            }

            public function send($data) {
                $this->messages[] = json_decode($data, true);
                return $this;
            }

            public function close() {
                $this->closed = true;
            }
        };
    }

    public function testStudentChannelCannotReceiveInstructorCountsOrPublishEvents(): void {
        TokenManager::initialize(str_repeat('a', 64), 'http://localhost');
        $core = $this->createMockCore(['debug' => false]);
        $core->getConfig()->method('getSecretSession')->willReturn('test-secret');
        $server = new Server($core);
        $token = TokenManager::generateWebSocketToken('student', 's26-sample-polls_student_index')->toString();
        $headers = ['Cookie' => 'submitty_websocket_token=' . $token];
        $student = $this->connection(1, 'polls_student_index', $headers);
        $server->onOpen($student);
        $this->assertFalse($student->closed);

        foreach (['polls_index', 'polls_student_index&course=other'] as $page) {
            $denied = $this->connection(2, $page, $headers);
            $server->onOpen($denied);
            $this->assertTrue($denied->closed);
            $this->assertSame([['error' => 'Authentication failed']], $denied->messages);
        }

        $instructor_token = TokenManager::generateWebSocketToken('instructor', 's26-sample-polls_index')->toString();
        $instructor = $this->connection(5, 'polls_index', ['Cookie' => 'submitty_websocket_token=' . $instructor_token]);
        $server->onOpen($instructor);
        $this->assertFalse($instructor->closed);
        $publisher = $this->connection(3, '', ['User-Agent' => 'websocket-client-php', 'Session-Secret' => 'test-secret']);
        $server->onOpen($publisher);
        $server->onMessage($publisher, json_encode([
            'user_id' => 'instructor', 'page' => 's26-sample-polls_index',
            'type' => 'update_num_responses', 'message' => 42
        ]));
        $this->assertSame([], $student->messages);
        $this->assertSame([['page' => 's26-sample-polls_index', 'type' => 'update_num_responses', 'message' => 42]], $instructor->messages);

        $server->onClose($publisher);
        $publisher = $this->connection(4, '', ['User-Agent' => 'websocket-client-php', 'Session-Secret' => 'test-secret']);
        $server->onOpen($publisher);
        $server->onMessage($publisher, json_encode([
            'user_id' => 'instructor', 'page' => 's26-sample-polls_student_index', 'type' => 'poll_state_changed'
        ]));
        $this->assertSame([['page' => 's26-sample-polls_student_index', 'type' => 'poll_state_changed']], $student->messages);
        $this->assertCount(1, $instructor->messages);

        $server->onMessage($student, json_encode([
            'user_id' => 'instructor', 'page' => 's26-sample-polls_student_index', 'type' => 'poll_state_changed'
        ]));
        $this->assertCount(1, $student->messages);
    }
}
