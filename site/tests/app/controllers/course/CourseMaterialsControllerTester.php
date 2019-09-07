<?php 

namespace tests\app\controllers\course;

use tests\BaseUnitTest;
use app\controllers\course\CourseMaterialsController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\Core;
use  \DateTime;

class CourseMaterialsTester extends BaseUnitTest{

	private $core;
	private $config;
	private $json_path;
	private $upload_path;

	public function setUp() : void{
		$this->config = array();
		$this->config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
		$this->config['use_mock_time'] = true;
		$_POST['csrf_token'] = "";
		$this->core = $this->createMockCore($this->config);
		$_POST['release_time'] = $this->core->getDateTimeNow()->format("Y-m-d H:i:s");


		FileUtils::createDir($this->core->getConfig()->getCoursePath() . "/uploads/course_materials" , null, true);
		$this->json_path = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
		$this->upload_path = $this->core->getConfig()->getCoursePath() . "/uploads/course_materials";
	}

	public function tearDown(): void {
        if (file_exists($this->config['course_path'])) {
            FileUtils::recursiveRmdir($this->config['course_path']);
        }
    }

    private function buildFakeFile($fd, $filename, $part = 1, $err = 0, $target_size = 100) {
        fseek($fd, $target_size-1,SEEK_CUR); 
        fwrite($fd,'a'); 
        fclose($fd);

        $_FILES["files{$part}"]['name'][] = $filename;
        $_FILES["files{$part}"]['type'][] = FileUtils::getMimeType($this->config['course_path'] . $filename);
        $_FILES["files{$part}"]['size'][] = filesize($this->config['course_path'] . $filename);

        $tmpname = $this->config['course_path'] . Utils::generateRandomString() . $filename;
        copy($this->config['course_path'] . $filename, $tmpname);

        $_FILES["files{$part}"]['tmp_name'][] = $tmpname;
        $_FILES["files{$part}"]['error'][] = $err;

    }

    public function testCourseMaterialsUpload(){
    	$controller = new CourseMaterialsController($this->core);

    	$name = "foo.txt";
        $tmpfile = fopen($this->config['course_path'] . $name, "w");
        $this->buildFakeFile($tmpfile, $name);

    	$ret = $controller->ajaxUploadCourseMaterialsFiles();

    	$json = FileUtils::readJsonFile($this->json_path);
      	//we need to check that the file exists in the correct folder and also the JSON file
      	$filename_full = FileUtils::joinPaths( $this->upload_path, $name );
      	$expected_json = array( "release_time" => $_POST['release_time'],
      							$filename_full => array(
      								"checked" => 1,
      								"release_datetime" => $_POST['release_time']
      							)
      						  );
      	$this->assertEquals($expected_json, $json);
      	//check the uploads directory now 
      	$files = FileUtils::getAllFiles($this->upload_path, array(), true);

      	$expected_files = array( $name => array(
      								'name' => $name,
      								'path' => $filename_full,
      								'size' => filesize($filename_full),
      								'relative_name' => $name
      								) 
      							);

      	$this->assertEquals($expected_files, $files);
    }

}