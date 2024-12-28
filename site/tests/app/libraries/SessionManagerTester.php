<?php

declare(strict_types=1);

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\SessionManager;
use app\entities\Session;
use app\repositories\SessionRepository;
use tests\BaseUnitTest;

class SessionManagerTester extends BaseUnitTest {
    private $browser_info = ['browser' => 'Browser', 'version' => '1.0', 'platform' => 'OS'];

    private function getRepoOnce($core, $repo) {
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(Session::class)
            ->willReturn($repo);
    }

    public function testGetSessionInvalidId() {
        $core = $this->createMockCore(['use_mock_time' => true]);
        $repo = $this->createMock(SessionRepository::class);
        $this->getRepoOnce($core, $repo);
        $session = $this->createMock(Session::class);
        $repo->expects($this->once())
            ->method('getActiveSessionById')
            ->with('id')
            ->willReturn(null);
        $session->expects($this->never())->method('updateSessionExpiration');
        $manager = new SessionManager($core);
        $this->assertFalse($manager->getSession('id'));
        $this->assertFalse($manager->getCsrfToken());
    }

    public function testSessionManager() {
        $core = $this->createMockCore(['use_mock_time' => true]);
        $repo = $this->createMock(SessionRepository::class);
        $this->getRepoOnce($core, $repo);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(Session::class)
            ->willReturn($repo);
        $session = $this->getMockBuilder(Session::class)
            ->onlyMethods(['updateSessionExpiration'])
            ->setConstructorArgs([
                'id',
                'test',
                'token',
                $core->getDateTimeNow()->add(\DateInterval::createFromDateString('2 weeks')),
                $core->getDateTimeNow(),
                $this->browser_info
            ])
            ->getMock();
        $repo->expects($this->once())
            ->method('getActiveSessionById')
            ->with('id')
            ->willReturn($session);
        $core->getSubmittyEntityManager()
            ->expects($this->exactly(0))
            ->method('persist');
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getReference')
            ->with(
                Session::class,
                $session->getSessionId()
            );
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('remove');
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('flush');
        $manager = new SessionManager($core);
        $this->assertEquals('test', $manager->getSession('id'));
        $this->assertEquals('id', $manager->newSession('test', $this->browser_info));
        $this->assertEquals('token', $manager->getCsrfToken());
        $this->assertTrue($manager->removeCurrentSession('id'));
        $this->assertFalse($manager->getCsrfToken());
    }

    public function testNewSession() {
        $core = $this->createMockCore(['use_mock_time' => true]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($session): bool {
                    $this->assertInstanceOf(Session::class, $session);
                    $this->assertEquals($session->getUser()->getId(), 'test');
                    $this->assertEquals($session->getBrowserName(), $this->browser_info['browser']);
                    $this->assertEquals($session->getBrowserVersion(), $this->browser_info['version']);
                    $this->assertEquals($session->getPlatform(), $this->browser_info['platform']);
                    return true;
                })
            );
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('persist');
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('flush');
        $manager = new SessionManager($core);
        $session_id = $manager->newSession('test', $this->browser_info);
        $this->assertMatchesRegularExpression('/[a-f0-9]{32}/', $session_id);
        $this->assertEquals($session_id, $manager->newSession('test', $this->browser_info));
        $this->assertMatchesRegularExpression('/[a-f0-9]{32}/', $manager->getCsrfToken());
    }

    public function testRemoveCurrentSessionNoSession() {
        $core = new Core();
        $manager = new SessionManager($core);
        $this->assertFalse($manager->getCsrfToken());
        $this->assertFalse($manager->removeCurrentSession());
    }
}
