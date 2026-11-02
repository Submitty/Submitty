<?php

namespace tests\app\controllers;

use app\controllers\PollController;
use app\controllers\GlobalController;
use app\entities\poll\Option;
use app\entities\poll\Poll;
use app\entities\poll\Response;
use app\libraries\PollUtils;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\response\RedirectResponse;
use app\models\Locale;
use app\repositories\poll\PollRepository;
use app\views\GlobalView;
use tests\BaseUnitTest;

class PollControllerTester extends BaseUnitTest {
    use \phpmock\phpunit\PHPMock;

    protected function tearDown(): void {
        $_POST = [];
        $_FILES = [];
        parent::tearDown();
    }

    public static function indexRoles(): array {
        return [
            'student' => [false, 'polls_student_index', 'showPollsStudent'],
            'instructor' => [true, 'polls_index', 'showPollsInstructor']
        ];
    }

    /**
     * @dataProvider indexRoles
     */
    public function testIndexAuthorizesOnlyItsOwnChannel(bool $admin, string $channel, string $view): void {
        $core = $this->createMockCore([], ['access_admin' => $admin]);
        $repo = $this->createMock(PollRepository::class);
        $repo->method('findAllByStudentIDWithAllOptions')->willReturn([]);
        $repo->method('findAllWithNumResponses')->willReturn([]);
        $core->getCourseEntityManager()->method('getRepository')->willReturn($repo);
        $core->expects($this->once())->method('authorizeWebSocketToken')->with(['page' => $channel]);

        $response = (new PollController($core))->showPollsPage();
        $this->assertSame($view, $response->view_function);
        $this->assertCount($admin ? 6 : 2, $response->parameters);
    }

    public static function listMutations(): array {
        return [
            'create' => ['addNewPoll'],
            'delete' => ['deletePoll']
        ];
    }

    private function poll(string $date = '2001-01-01'): Poll {
        $poll = new Poll('Poll', 'Question', 'single-response-single-correct', new \DateInterval('PT10M'), new \DateTime($date), 'never', 'never');
        (new \ReflectionProperty(Poll::class, 'id'))->setValue($poll, 1);
        return $poll;
    }

    private function mutationPost(): array {
        return [
            'poll_id' => '1', 'name' => 'New poll', 'question' => 'Question',
            'question_type' => 'single-response-single-correct', 'release_date' => '2001-01-01',
            'release_histogram' => 'never', 'release_answer' => 'never',
            'option' => [['order' => '0', 'response' => 'Private option', 'is_correct' => 'on']]
        ];
    }

    private function mutationController(bool &$flushed): PollController {
        $core = $this->createMockCore();
        $em = $core->getCourseEntityManager();
        $repo = $this->createMock(PollRepository::class);
        $repo->method('findByIDWithOptions')->willReturn($this->poll());
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush')->willReturnCallback(function () use (&$flushed): void {
            $flushed = true;
        });
        $controller = $this->getMockBuilder(PollController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['sendSocketMessage'])
            ->getMock();
        $controller->expects($this->once())->method('sendSocketMessage')->with(
            $this->callback(function ($message) use (&$flushed): bool {
                $this->assertTrue($flushed, 'Notify only after successful persistence');
                return $message === ['type' => 'poll_state_changed'];
            }),
            'polls_student_index'
        );
        $_POST = $this->mutationPost();
        return $controller;
    }

    /**
     * @dataProvider listMutations
     */
    public function testListMutationNotifiesStudentsAfterFlush(string $method): void {
        $flushed = false;
        $controller = $this->mutationController($flushed);
        $controller->$method();
        $this->assertTrue($flushed);
    }

    /**
     * @dataProvider listMutations
     */
    public function testFailedPersistenceDoesNotNotifyStudents(string $method): void {
        $core = $this->createMockCore();
        $repo = $this->createMock(PollRepository::class);
        $repo->method('findByIDWithOptions')->willReturn($this->poll());
        $em = $core->getCourseEntityManager();
        $em->method('getRepository')->willReturn($repo);
        $em->method('flush')->willThrowException(new \RuntimeException('Persistence failed'));
        $controller = $this->getMockBuilder(PollController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['sendSocketMessage'])
            ->getMock();
        $controller->expects($this->never())->method('sendSocketMessage');
        $_POST = $this->mutationPost();
        $this->expectExceptionMessage('Persistence failed');
        $controller->$method();
    }

    public function testImportedPollsNotifyAfterPersistence(): void {
        $flushed = false;
        $controller = $this->mutationController($flushed);
        $_FILES = ['polls_file' => ['tmp_name' => '/uploaded-polls.json']];
        $this->getFunctionMock('app\libraries', 'is_file')->expects($this->once())->with('/uploaded-polls.json')->willReturn(true);
        $this->getFunctionMock('app\libraries', 'file_get_contents')->expects($this->once())->with('/uploaded-polls.json')
            ->willReturn(json_encode(PollUtils::getPollExportData([$this->poll()])));
        $controller->importPollsFromJSON();
        $this->assertTrue($flushed);
    }

    public function testStudentIndexKeepsFuturePollsOutOfBothGroups(): void {
        $core = $this->createMockCore(['use_mock_time' => true]);
        $repo = $this->createMock(PollRepository::class);
        $today = $this->poll('2001-01-01');
        $older = $this->poll('2000-12-31');
        $future = $this->poll('2001-01-02');
        $repo->method('findAllByStudentIDWithAllOptions')->with('testUser')->willReturn([$older, $today, $future]);
        $core->getCourseEntityManager()->method('getRepository')->willReturn($repo);
        $response = (new PollController($core))->showPollsPage();
        $this->assertSame([[$today], [$older]], $response->parameters);
    }

    public static function backgroundIndexRoles(): array {
        return ['student' => [false], 'instructor' => [true]];
    }

    public function testBackgroundIndexRoute(): void {
        $routes = (new \app\libraries\routers\AttributeRouteLoader())->load(PollController::class);
        $matcher = new \Symfony\Component\Routing\Matcher\UrlMatcher($routes, new \Symfony\Component\Routing\RequestContext());
        $match = $matcher->match('/courses/f26/sample/polls/student_index');
        $this->assertSame('showStudentIndex', $match['_method']);
        $this->assertSame('f26', $match['_semester']);
        $this->assertSame('sample', $match['_course']);
    }

    /**
     * @dataProvider backgroundIndexRoles
     */
    public function testBackgroundIndexAlwaysUsesStudentVisibility(bool $admin): void {
        $core = $this->createMockCore([], ['access_admin' => $admin]);
        $repo = $this->createMock(PollRepository::class);
        $repo->expects($this->once())->method('findAllByStudentIDWithAllOptions')->with('testUser')->willReturn([]);
        $repo->expects($this->never())->method('findAllWithNumResponses');
        $core->getCourseEntityManager()->method('getRepository')->willReturn($repo);
        $core->expects($this->once())->method('authorizeWebSocketToken')->with(['page' => 'polls_student_index']);
        $response = (new PollController($core))->showStudentIndex();
        $this->assertSame('success', $response->json['status']);
    }

    public function testStudentAnswerPropsAreEscapedAndContainOnlyOwnResponse(): void {
        $this->createMockCore();
        $poll = $this->poll();
        $content = '**My answer** "quoted" <script>alert(1)</script> & more';
        $option = new Option(0, $content, false);
        (new \ReflectionProperty(Option::class, 'id'))->setValue($option, 1);
        $poll->addOption($option);
        $poll->addOption(new Option(1, 'Secret correct answer', true));
        $poll->addResponse(new Response('testUser'), 1);
        $twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader(dirname(__DIR__, 3) . '/app/templates'), ['autoescape' => 'html']);
        $twig->addFunction(new \Twig\TwigFunction('uniqid', 'uniqid'));
        $html = $twig->render('polls/AllPollsPageStudent.twig', [
            'todays_polls' => [$poll], 'older_polls' => [], 'base_url' => '/polls', 'csrf_token' => 'test'
        ]);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $mounts = (new \DOMXPath($dom))->query('//*[@class="student-poll-answer"]');
        $this->assertCount(1, $mounts);
        $this->assertSame($content, $mounts[0]->getAttribute('data-content'));
        $this->assertSame('', $mounts[0]->textContent);
        $this->assertStringNotContainsString('Secret correct answer', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testBackgroundIndexPreservesClosedPollErrorForVisiblePage(): void {
        $saved_session = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $dependencies = $this->createMockCore();
            $methods = ['getConfig', 'getUser', 'getAccess', 'getCourseEntityManager', 'getDateTimeNow'];
            $core = $this->getMockBuilder(Core::class)
                ->onlyMethods(array_merge($methods, ['authorizeWebSocketToken', 'getCsrfToken', 'buildCourseUrl', 'getSubmittyQueries', 'getCourseQueries']))
                ->getMock();
            foreach ($methods as $method) {
                $core->method($method)->willReturnCallback(fn() => $dependencies->$method());
            }
            $core->method('buildCourseUrl')->willReturn('/polls');
            $locale = $this->createMock(Locale::class);
            $locale->method('getName')->willReturn('en');
            $core->getConfig()->method('getLocale')->willReturn($locale);
            $repo = $this->createMock(PollRepository::class);
            $repo->method('findByStudentID')->willReturn($this->poll());
            $repo->method('findAllByStudentIDWithAllOptions')->willReturn([]);
            $core->getCourseEntityManager()->method('getRepository')->willReturn($repo);

            // Use the real output/header decision and flash handling; isolate unrelated layout queries.
            $output = $this->getMockBuilder(Output::class)->setConstructorArgs([$core])
                ->onlyMethods(['renderTemplate', 'renderTwigTemplate'])->getMock();
            $output->method('renderTemplate')->willReturn('student index');
            $output->method('renderTwigTemplate')->willReturnCallback(fn($template, $args) => json_encode($args['messages']));
            $core->setOutput($output);
            $global = $this->createMock(GlobalController::class);
            $global->method('header')->willReturnCallback(fn() => (new GlobalView($core, $output))->header([], [], [], null, [], [], [], null, 'Polls', false, [], false));
            $global->method('footer')->willReturn('');
            (new \ReflectionProperty(Output::class, 'controller'))->setValue($output, $global);

            $controller = new PollController($core);
            $redirect = $controller->showPoll('1');
            $this->assertInstanceOf(RedirectResponse::class, $redirect);
            $this->assertSame('/polls', $redirect->url);
            $this->assertSame(['Poll is not available'], $_SESSION['messages']['error']);

            // A background refresh wins the session lock before the visible redirect target.
            $controller->showStudentIndex()->render($core);
            $background = $output->getOutput();
            $this->assertStringNotContainsString('Poll is not available', $background);
            $this->assertSame(['Poll is not available'], $_SESSION['messages']['error']);

            // A fresh, visible page has its normal global chrome enabled.
            $output->useHeader(true);
            $output->useFooter(true);
            $controller->showPollsPage()->render($core);
            $this->assertStringContainsString('Poll is not available', $output->getOutput());
            $this->assertSame([], $_SESSION['messages']['error']);
        }
        finally {
            $_SESSION = $saved_session;
        }
    }
}
