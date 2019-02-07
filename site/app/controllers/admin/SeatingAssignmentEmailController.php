<?php

namespace app\controllers\admin;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\FileUtils;


class SeatingAssignmentEmail extends AbstractController {


	public function __construct(Core $core) {
        parent::__construct($core);
    }

	public function run() {
	    switch ($_REQUEST['page']) {
	    	case 'edit_seating_assignment_email':
	    		$this->emailSeatingAssignments();
	    		break; 
	    	

	    }
	}


	public function emailSeatingAssignments() {
		$seating_assignment_subject = $_POST["room_seating_email_subject"];
		$seating_assignment_body = $_POST["room_seating_email_body"];
		//TODO: construct validation/error checking 

		$gradeable_id = $this->core->getConfig()->getRoomSeatingGradeableId();
		$course =  $this->core->getConfig()->getCourse();

		$seating_assignments_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", "seating", $gradeable_id);

		$seating_dir = new \DirectoryIterator($seating_assignments_path);
        foreach ($seating_dir as $seatingAssignmentFile) {

        	if (!$seatingAssignmentFile->isDot() && $seatingAssignmentFile->getExtension() === "json") {
	        	$seating_assignment_data = FileUtils::readJsonFile($seatingAssignmentFile->getPathname());

	        	$email_data = [
	                "subject" => $this->replaceSeatingAssignmentDataPlaceholders($seating_assignment_subject, $seating_assignment_data),
	                "body" => $this->replaceSeatingAssignmentDataPlaceholders($seating_assignment_body, $seating_assignment_data)
	            ];

	            $recipient = $seatingAssignmentFile->getBasename('.json');

	        	$this->core->getQueries()->createEmail($email_data, $recipient);
        	}
    	}

    	$result = ['success' => 'Sucess! Seating assignment emails have been sent!'];
    	$this->core->getOutput()->renderJson($result);
    	return $this->core->getOutput()->getOutput();

	}

	private function replaceSeatingAssignmentDataPlaceholders($seatingAssignmentMessage, $seatingAssignmentData) {

		$seatingAssignmentMessage = str_replace("{gradeable_id}", $seatingAssignmentData["gradeable"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{course_name}", $this->core->getConfig()->getCourse(), $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_date}", $seatingAssignmentData["date"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_time}", $seatingAssignmentData["time"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_building}", $seatingAssignmentData["building"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_room}", $seatingAssignmentData["room"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_zone}", $seatingAssignmentData["zone"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_row}", $seatingAssignmentData["row"], $seatingAssignmentMessage); 
		$seatingAssignmentMessage = str_replace("{exam_seat}", $seatingAssignmentData["seat"], $seatingAssignmentMessage);

		return $seatingAssignmentMessage;
	}



}
