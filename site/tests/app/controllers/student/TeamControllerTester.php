<?php

namespace tests\app\controllers\student;

use tests\BaseUnitTest;
use app\controllers\student\TeamController;
use app\models\gradeable\Gradeable;
use app\libraries\FileUtils;
use app\libraries\Utils;
use ReflectionObject;

class TeamControllerTester extends BaseUnitTest {

    private $core;

    private $config = [];

    public function setUp(): void {
        $config['gradeable_id'] = "test";

        $config['semester'] = "test";
        $config['course'] = "test";

        $config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['gradeable_path'] = FileUtils::joinPaths($config['course_path'], "submissions", $config['gradeable_id']);

        $this->config = $config;
        $this->core = $this->createMockCore($this->config);
    }
    //remove any external resources here
    public function tearDown(): void {
        $this->assertTrue(FileUtils::recursiveRmdir($this->config['course_path']));
    }

    //Test making teams
    public function testCreateTeamOnNullGradeable() {
        $controller = new TeamController($this->core);
        $response = $controller->createNewTeam(false);
        $this->assertEquals(["status" => "fail", "message" => "Invalid or missing gradeable id!"], $response);
    }

    //create a normal gradeable, we should not be able to create a team
    public function testCreateTeamOnNonTeamGradeable() {
        $this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn($this->createMockGradeable(false));
        $controller = new TeamController($this->core);
        $response = $controller->createNewTeam($this->config['gradeable_id']);
        $this->assertEquals(["status" => "fail", "message" => "Test Gradeable is not a team assignment"], $response);
    }

    public function testCreateTeamSuccess() {
        $this->config['use_mock_time'] = true;
        $this->core = $this->createMockCore($this->config);

        $mock_gradeable = $this->createMockGradeable();
        $this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn($mock_gradeable);
        $this->core->getQueries()->method('createTeam')->willReturn('test');
        $controller = new TeamController($this->core);

        $this->core->getQueries()->method('createTeam')->willReturn("test");

        //build folders for new team
        $this->assertTrue(FileUtils::createDir($this->config['gradeable_path'], true));
        $tmp = FileUtils::joinPaths($this->config['gradeable_path'], "test");
        $this->assertTrue(FileUtils::createDir($tmp, true));

        $response = $controller->createNewTeam($this->config['gradeable_id']);
        $this->assertEquals(["status" => "success", "data" => null], $response);

        $settings_file = FileUtils::joinPaths($this->config['gradeable_path'], "test", "user_assignment_settings.json");
        $this->assertTrue(file_exists($settings_file));

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();

        $team_history = FileUtils::encodeJson(["team_history" => [["action" => "create", "time" => $current_time, "user" => "testUser"]]]);

        $this->assertJsonStringEqualsJsonFile($settings_file, $team_history);
    }

    private function createMockGradeable($is_team = true) {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");
        if ($is_team) {
            $gradeable->method('isTeamAssignment')->willReturn(true);
        }

        return $gradeable;
    }

    /** @dataProvider provideTestCleanInviteId */
    public function testCleanInviteId(string $raw, string $expectation) {
        $controller = new ReflectionObject(new TeamController($this->core));
        $clean_invite_id_method = $controller->getMethod('cleanInviteId');
        $clean_invite_id_method->setAccessible(true);

        $clean_invite_id = $clean_invite_id_method->invoke(null, $raw);
        $this->assertEquals($clean_invite_id, $expectation);
    }

    public function provideTestCleanInviteId(): array {
        return [
            'assert removes whitespace' => ['      rcsid    ', 'rcsid'],
            'assert converts all chars to lowercase' => ['      RcSiD    ', 'rcsid'],
            'assert keeps numbers' => ['rcsid1', 'rcsid1'],
        ];
    }
}
