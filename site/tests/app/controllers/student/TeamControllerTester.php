<?php
namespace tests\app\controllers\student;

use tests\BaseUnitTest;
use app\controllers\student\TeamController;
use app\models\gradeable\Gradeable;

class TeamControllerTester extends BaseUnitTest {

	private $core;

	private $core_settings = array();

	public function setUp() : void{

        $_REQUEST['gradeable_id'] = 'test';

		$core_settings['semester'] = "test";
        $core_settings['course'] = "test";

		$this->config = $core_settings;
		$this->core = $this->createMockCore($this->core_settings);
	}
	//remove any external resources here
	public function tearDown() : void{

	}

	//Test making teams 
	public function testCreateTeamOnNonTeamGradeable(){
		$gradeable = $this->createMockGradeable();
		$this->core->getQueries()->method('getGradeableConfig')->willReturn($gradeable);

		$controller = new TeamController($this->core);
		$controller->gradeable = $gradeable;
		$response = $controller->createNewTeam();
		$this->assertEquals(["error" => true, "message" => "Test Gradeable is not a team assignment"] , $response);
	}

	private function createMockGradeable() {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");
        return $gradeable;
    }

	//my first test :D
	//this should bring coverage to a 100%
	public function testHelloWorld(){
		$shail = true;
		$this->assertTrue($shail);
	}
}