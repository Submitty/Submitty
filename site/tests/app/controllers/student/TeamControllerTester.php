<?php
namespace tests\app\controllers\student;

use tests\BaseUnitTest;
use app\controllers\student\TeamController;
use app\models\gradeable\Gradeable;

class TeamControllerTester extends BaseUnitTest {

	private $core;

	private $core_settings = array();

	public function setUp() : void{
		$_REQUEST['gradeable_id'] = "test";

		$core_settings['semester'] = "test";
        $core_settings['course'] = "test";

		$this->config = $core_settings;
		$this->core = $this->createMockCore($this->core_settings);
	}
	//remove any external resources here
	public function tearDown() : void{

	}

	//Test making teams 
	public function testCreateTeamOnNullGradeable(){
		$this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn(false);
		$_REQUEST['action'] = 'create_new_team';
		$controller = new TeamController($this->core);
		$response = $controller->run();
		$this->assertEquals(["error" => true, "message" => "Invalid or missing gradeable id!"] , $response);
	}

	//create a normal gradeable, we should not be able to create a team 
	public function testCreateTeamOnNonTeamGradeable(){
		$this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn($this->createMockGradeable());
		$_REQUEST['action'] = 'create_new_team';
		$controller = new TeamController($this->core);
		$response = $controller->run();
		$this->assertEquals(["error" => true, "message" => "Test Gradeable is not a team assignment"] , $response);
	}

	private function createMockGradeable() {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");
        return $gradeable;
    }
}