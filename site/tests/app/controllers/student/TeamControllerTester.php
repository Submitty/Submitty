<?php
namespace tests\app\controllers\student;

use tests\BaseUnitTest;
use app\controllers\student\TeamController;
use app\models\gradeable\Gradeable;
use app\libraries\FileUtils;
use app\libraries\Utils;

class TeamControllerTester extends BaseUnitTest {

	private $core;

	private $config = array();

	public function setUp() : void{
		$_REQUEST['gradeable_id'] = "test";

		$config['semester'] = "test";
        $config['course'] = "test";

        $config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
    	$config['gradeable_path'] = FileUtils::joinPaths($config['course_path'], "submissions", $_REQUEST['gradeable_id'],
            $config['course_path']);

		$this->config = $config;
		$this->core = $this->createMockCore($this->config);
	}
	//remove any external resources here
	public function tearDown() : void{
		$this->assertTrue(FileUtils::recursiveRmdir($this->config['course_path']));
	}

	//Test making teams 
	public function testCreateTeamOnNullGradeable(){
		$this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn(false);
		$_REQUEST['action'] = 'create_new_team';
		$controller = new TeamController($this->core);
		$response = $controller->run();
		$this->assertEquals(["status" => "fail", "message" => "Invalid or missing gradeable id!"] , $response);
	}

	//create a normal gradeable, we should not be able to create a team 
	public function testCreateTeamOnNonTeamGradeable(){
		$this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn($this->createMockGradeable(false));
		$_REQUEST['action'] = 'create_new_team';
		$controller = new TeamController($this->core);
		$response = $controller->run();
		$this->assertEquals(["status" => "fail", "message" => "Test Gradeable is not a team assignment"] , $response);
	}

	public function testCreateTeamSuccess(){
		$mock_gradeable = $this->createMockGradeable();
		$this->core->getQueries()->method('getGradeableConfig')->with('test')->willReturn($mock_gradeable);
		$_REQUEST['action'] = 'create_new_team';
		$controller = new TeamController($this->core);
		//build folders for new team
		$this->assertTrue(FileUtils::createDir($this->config['gradeable_path'], null, true));
		$this->core->getQueries()->method('createTeam')->willReturn("test");
		$tmp = FileUtils::joinPaths($this->config['gradeable_path'], "test");
		$this->assertTrue(FileUtils::createDir($tmp, null, true));

		$response = $controller->run();

		$this->assertEquals(["status" => "success", "data" => null] , $response);
	}

	private function createMockGradeable($is_team = true) {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");
        if($is_team){
        	$gradeable->method('isTeamAssignment')->willReturn(true);
        }

        return $gradeable;
    }
}