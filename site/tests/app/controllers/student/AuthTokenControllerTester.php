<?php

namespace tests\app\controllers\student;

use app\controllers\student\AuthTokenController;
use app\entities\VcsAuthToken;
use app\libraries\Core;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\repositories\VcsAuthTokenRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\MockObject\MockObject;
use tests\BaseUnitTest;

class AuthTokenControllerTester extends BaseUnitTest {
    private $tokens = [];

    public function setUp(): void {
        $this->tokens = [];
    }

    private function makeCore(bool $removeToken = false): Core {
        $core = $this->createMockCore();

        $this->makeTokens($core, 5);

        $repo = $this->createMock(VcsAuthTokenRepository::class);
        $repo->method('getAllByUser')->with('testUser', true)
            ->willReturn($this->tokens);

        /** @var MockObject & EntityManager $em */
        $em = $core->getSubmittyEntityManager();
        $em->method('persist')->willReturnCallback(function (VcsAuthToken $token) {
            $class = new \ReflectionClass($token);
            $prop = $class->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($token, 0);
        });
        if ($removeToken) {
            $em->expects($this->once())->method('remove');
            $repo->method('find')->willReturn($this->tokens[0]);
        }
        $em->method('getRepository')->willReturn($repo);

        return $core;
    }

    private function makeTokens(Core $core, int $numTokens) {
        for ($i = 0; $i < $numTokens; $i++) {
            $token = new VcsAuthToken(
                'testUser',
                'TOKEN',
                'name',
                $core->getDateTimeNow()
            );

            $class = new \ReflectionClass($token);
            $prop = $class->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($token, $i);

            $this->tokens[] = $token;
        }
    }

    public function testRetrievingTokens() {
        $core = $this->makeCore();

        $controller = new AuthTokenController($core);

        $response = $controller->vcsAuthTokens();

        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertContains($this->tokens, $response->parameters);
    }

    public function testMakingNewToken() {
        $core = $this->makeCore();

        $controller = new AuthTokenController($core);

        $response = $controller->vcsAuthTokens();

        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertContains($this->tokens, $response->parameters);

        $_POST['name'] = 'newToken';
        $_POST['expiration'] = 0;

        $response = $controller->createVcsAuthToken();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($core->buildUrl(['authentication_tokens']), $response->url);

        $this->assertArrayHasKey('new_auth_token', $_SESSION);
        $this->assertArrayHasKey('new_auth_token_id', $_SESSION);
        $this->assertEquals(64, strlen($_SESSION['new_auth_token']));
        $this->assertEquals(0, $_SESSION['new_auth_token_id']);
    }

    public function testRevokingToken() {
        $core = $this->makeCore(true);
        $_POST['id'] = 0;

        $controller = new AuthTokenController($core);

        $response = $controller->revokeVcsToken();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($core->buildUrl(['authentication_tokens']), $response->url);
    }
}
