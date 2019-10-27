<?php declare(strict_types = 1);

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\SessionManager;
use app\libraries\database\DatabaseQueries;

class SessionManagerTester extends \PHPUnit\Framework\TestCase {
    public function testGetSessionInvalidId() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->expects($this->once())->method('removeExpiredSessions');
        $queries->expects($this->once())->method('getSession')->with('id')->willReturn([]);
        $queries->expects($this->never())->method('updateSessionExpiration');
        $core->setQueries($queries);
        $manager = new SessionManager($core);
        $this->assertFalse($manager->getSession('id'));
        $this->assertFalse($manager->getCsrfToken());
    }

    public function testSessionManager() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->expects($this->once())->method('removeExpiredSessions');
        $queries->expects($this->once())->method('getSession')->with('id')->willReturn([
            'session_id' => 'id',
            'user_id' => 'test',
            'csrf_token' => 'token'
        ]);
        $queries->expects($this->once())->method('updateSessionExpiration')->with('id');
        $queries->expects($this->once())->method('removeSessionById')->with('id');
        $core->setQueries($queries);
        $manager = new SessionManager($core);
        $this->assertEquals('test', $manager->getSession('id'));
        $this->assertEquals('id', $manager->newSession('test'));
        $this->assertEquals('token', $manager->getCsrfToken());
        $this->assertTrue($manager->removeCurrentSession('id'));
        $this->assertFalse($manager->getCsrfToken());
    }

    public function testNewSession() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->expects($this->once())->method('newSession')->with(
            $this->matchesRegularExpression('/[a-f0-9]{32}/'),
            'test',
            $this->matchesRegularExpression('/[a-f0-9]{32}/')
        );
        $core->setQueries($queries);
        $manager = new SessionManager($core);
        $session_id = $manager->newSession('test');
        $this->assertRegExp('/[a-f0-9]{32}/', $session_id);
        $this->assertEquals($session_id, $manager->newSession('test'));
        $this->assertRegExp('/[a-f0-9]{32}/', $manager->getCsrfToken());
    }

    public function testRemoveCurrentSessionNoSession() {
        $core = new Core();
        $manager = new SessionManager($core);
        $this->assertFalse($manager->getCsrfToken());
        $this->assertFalse($manager->removeCurrentSession());
    }
}
