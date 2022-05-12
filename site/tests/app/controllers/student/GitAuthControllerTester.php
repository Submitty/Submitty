<?php

namespace tests\app\controllers\student;

use app\controllers\student\GitAuthController;
use app\entities\GitAuthToken;
use app\libraries\Core;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\repositories\GitAuthTokenRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\MockObject\MockObject;
use tests\BaseUnitTest;

class GitAuthControllerTester extends BaseUnitTest {
    private $tokens = [];

    public function setUp(): void {
        $this->tokens = [];
    }

    private function makeCore(bool $removeToken = false): Core {
        $core = $this->createMockCore();

        $this->makeTokens($core, 5);

        $repo = $this->createMock(GitAuthTokenRepository::class);
        $repo->method('getAllByUser')->with('testUser', true)
            ->willReturn($this->tokens);

        /** @var MockObject & EntityManager $em */
        $em = $core->getSubmittyEntityManager();
        if ($removeToken) {
            $em->expects($this->once())->method('remove');
            $repo->method('find')->willReturn($this->tokens[0]);
        }
        $em->method('getRepository')->willReturn($repo);

        return $core;
    }

    private function makeTokens(Core $core, int $numTokens) {
        for ($i = 0; $i < $numTokens; $i++) {
            $this->tokens[] = new GitAuthToken(
                'testUser',
                'TOKEN',
                'name',
                $core->getDateTimeNow()
            );
        }
    }

    public function testRetrievingTokens() {
        $core = $this->makeCore();

        $controller = new GitAuthController($core);

        $response = $controller->gitAuthTokens();

        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertContains($this->tokens, $response->parameters);
    }

    public function testMakingNewToken() {
        $core = $this->makeCore();

        $controller = new GitAuthController($core);

        $response = $controller->gitAuthTokens();

        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertContains($this->tokens, $response->parameters);

        $_POST['name'] = 'newToken';
        $_POST['expiration'] = 0;

        $response = $controller->createGitAuthToken();

        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertEquals(3, count($response->parameters));
        $this->assertEquals(64, strlen($response->parameters[2]));

        $this->assertContains($this->tokens, $response->parameters);
    }

    public function testRevokingToken() {
        $core = $this->makeCore();

        $controller = new GitAuthController($core);

        $response = $controller->revokeToken();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($core->buildUrl(['git_auth_tokens']), $response->url);
    }
}
