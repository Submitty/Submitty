<?php

namespace tests\app\controllers\admin;

use app\models\User;
use app\models\SimpleLateUser;
use app\models\Team;
use app\libraries\Core;
use tests\BaseUnitTest;
use app\libraries\response\WebResponse;
use app\controllers\admin\LateController;

class LateControllerTester extends BaseUnitTest {
   /** @var LateController */
    protected $controller;

    /** @var Core */
    protected $core;

    /** @var User */
    protected $user;

    public function setUp(): void {
        parent::setUp();

        $this->core = $this->createMockCore([], [], [
            'getUsersWithLateDays'  => [],
            'getAllUsers'           => [],
        ]);

        $this->controller = new LateController($this->core);
    }

    public function testQueriesCalledinController() {
        $response = $this->controller->viewLateDays();

        $this->assertMethodCalled('getUsersWithLateDays');
        $this->assertMethodCalled('getAllUsers');
        $this->assertInstanceOf(WebResponse::class, $response);
    }

    public static function extensionUpdateProvider(): array {
        return [
            'reason only' => ['2', 'travel', true],
            'days only' => ['3', 'illness', true],
            'both fields' => ['3', 'late add', true],
            'unchanged' => ['2', 'illness', false],
            'delete' => ['0', '', true],
        ];
    }

    /** @dataProvider extensionUpdateProvider */
    public function testUpdateExistingExtension(string $days, string $reason, bool $changed): void {
        $original_post = $_POST;
        $original_files = $_FILES;
        $_POST = [
            'g_id' => 'homework',
            'user_id' => 'student',
            'late_days' => $days,
            'reason_for_exception' => $reason,
        ];
        $_FILES = [];
        try {
            $user = $this->createMockModel(User::class);
            $extension = $this->createMockModel(SimpleLateUser::class);
            $extension->method('getId')->willReturn('student');
            $extension->method('getLateDayExceptions')->willReturn(2);
            $extension->method('getReasonForException')->willReturn('illness');
            $queries = $this->core->getQueries();
            $queries->method('getUsersById')->willReturn([$user]);
            $queries->method('getUsersWithExtensions')->willReturn([$extension]);
            $queries->method('getTeamByGradeableAndUser')->willReturn(null);
            $queries->expects($changed ? $this->once() : $this->never())
                ->method('updateExtensions')
                ->with('student', 'homework', $days, $reason);

            $response = $this->controller->updateExtension();
            $this->assertSame('success', $response->json_response->json['status']);
        }
        finally {
            $_POST = $original_post;
            $_FILES = $original_files;
        }
    }

    public static function teamExtensionProvider(): array {
        return [[-1, []], [0, ['student']], [1, ['student', 'teammate']]];
    }

    /** @dataProvider teamExtensionProvider */
    public function testReasonOnlyTeamExtension(int $option, array $expected_users): void {
        $original_post = $_POST;
        $original_files = $_FILES;
        $_POST = ['g_id' => 'homework', 'user_id' => 'student', 'late_days' => '2', 'reason_for_exception' => 'travel', 'option' => $option];
        $_FILES = [];
        try {
            $user = $this->createMockModel(User::class);
            $user->method('getDisplayedGivenName')->willReturn('Example');
            $user->method('getDisplayedFamilyName')->willReturn('Student');
            $extension = $this->createMockModel(SimpleLateUser::class);
            $extension->method('getId')->willReturn('student');
            $extension->method('getLateDayExceptions')->willReturn(2);
            $extension->method('getReasonForException')->willReturn('illness');
            $team = $this->createMockModel(Team::class);
            $team->method('getSize')->willReturn(2);
            $team->method('getMemberList')->willReturn('student, teammate');
            $queries = $this->core->getQueries();
            $queries->method('getUsersById')->willReturn([$user]);
            $queries->method('getUserById')->willReturn($user);
            $queries->method('getUsersWithExtensions')->willReturn([$extension]);
            $queries->method('getTeamByGradeableAndUser')->willReturn($team);
            $updated_users = [];
            $queries->expects($this->exactly(count($expected_users)))->method('updateExtensions')
                ->willReturnCallback(function ($user_id, $g_id, $days, $reason) use (&$updated_users): void {
                    $updated_users[] = $user_id;
                    $this->assertSame(['homework', '2', 'travel'], [$g_id, $days, $reason]);
                });
            $core = $this->createMock(Core::class);
            $core->method('getQueries')->willReturn($queries);
            $output = $this->createMock(\app\libraries\Output::class);
            $output->expects($option === -1 ? $this->once() : $this->never())
                ->method('renderTwigTemplate')->willReturn('<div>Team Update</div>');
            $core->method('getOutput')->willReturn($output);
            $response = (new LateController($core))->updateExtension();
            $this->assertSame('success', $response->json_response->json['status']);
            $this->assertSame($expected_users, $updated_users);
            if ($option === -1) {
                $this->assertTrue($response->json_response->json['data']['is_team']);
            }
        }
        finally {
            $_POST = $original_post;
            $_FILES = $original_files;
        }
    }
}
