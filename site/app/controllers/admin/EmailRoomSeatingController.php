<?php

namespace app\controllers\admin;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\FileUtils;


class EmailRoomSeatingController extends AbstractController {
	const DEFAULT_EMAIL_SUBJECT = '[Submitty {$course_name}]: Seating Assignment for {$gradeable_id}';
	const DEFAULT_EMAIL_BODY =
'Hello,

Listed below is your seating assignment for the upcoming exam {$gradeable_id} on {$exam_date} at {$exam_time}.

Location: {$exam_building}
Exam Room: {$exam_room}
Zone: {$exam_zone}
Row: {$exam_room}
Seat: {$exam_seat}

Please email your instructor with any questions or concerns.';

 public function __construct(Core $core) {
        parent::__construct($core);
    }

	public function run() {

		switch($_REQUEST['action']) {
				case 'send_email':
						$this->emailSeatingAssignments();
				case 'show_page':
				default:
						$this->renderEmailTemplate();
						break;
		}
	}

	private function renderEmailTemplate(){
		$this->core->getOutput()->renderOutput(array('admin', 'EmailRoomSeating'), 'displayPage', EmailRoomSeatingController::DEFAULT_EMAIL_SUBJECT, EmailRoomSeatingController::DEFAULT_EMAIL_BODY);
	}


	public function emailSeatingAssignments() {
		$seating_assignment_subject = $_POST["room_seating_email_subject"];
		$seating_assignment_body = $_POST["room_seating_email_body"];

		try {
			$gradeable_id = $this->core->getConfig()->getRoomSeatingGradeableId();
			$course =  $this->core->getConfig()->getCourse();

			$seating_assignments_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", "seating", $gradeable_id);

			$seating_dir = new \DirectoryIterator($seating_assignments_path);
	        foreach ($seating_dir as $seatingAssignmentFile) {

	        	if (!$seatingAssignmentFile->isDot() && $seatingAssignmentFile->getExtension() === "json") {
		        	$seating_assignment_data = FileUtils::readJsonFile($seatingAssignmentFile->getPathname());

		        	$email_data = [
		                "subject" => $this->replaceSeatingAssignmentMessagePlaceholders($seating_assignment_subject, $seating_assignment_data),
		                "body" => $this->replaceSeatingAssignmentMessagePlaceholders($seating_assignment_body, $seating_assignment_data)
		            ];


							$recipient_id = $seatingAssignmentFile->getBasename('.json');
							$recipient = $this->core->getQueries()->getSubmittyUser($recipient_id);

							if($recipient == null || $this->core->getQueries()->hasDroppedCourse($recipient_id)){
								continue;
							}

							$this->core->getQueries()->createEmail($email_data, $recipient->getEmail());

	        	}
	    	}
				$this->core->addSuccessMessage("Seating assignments have been sucessfully emailed!");

		} catch (\Exception $e) {
			$this->core->getOutput()->renderJsonError($e->getMessage());
		}
			return $this->core->redirect($this->core->buildUrl());

	}

	private function replaceSeatingAssignmentMessagePlaceholders($seatingAssignmentMessage, $seatingAssignmentData) {
		if(array_key_exists("gradeable", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$gradeable_id}', $seatingAssignmentData["gradeable"], $seatingAssignmentMessage);
		}
		if(array_key_exists("date", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_date}', $seatingAssignmentData["date"], $seatingAssignmentMessage);
		}
		if(array_key_exists("time", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_time}', $seatingAssignmentData["time"], $seatingAssignmentMessage);
		}
		if(array_key_exists("building", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_building}', $seatingAssignmentData["building"], $seatingAssignmentMessage);
		}
		if(array_key_exists("room", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_room}', $seatingAssignmentData["room"], $seatingAssignmentMessage);
		}
		if(array_key_exists("zone", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_zone}', $seatingAssignmentData["zone"], $seatingAssignmentMessage);
		}
		if(array_key_exists("row", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_row}', $seatingAssignmentData["row"], $seatingAssignmentMessage);
		}
		if(array_key_exists("seat", $seatingAssignmentData)){
			$seatingAssignmentMessage = str_replace('{$exam_seat}', $seatingAssignmentData["seat"], $seatingAssignmentMessage);
		}

		$seatingAssignmentMessage = str_replace('{$course_name}', $this->core->getConfig()->getCourse(), $seatingAssignmentMessage);
		return $seatingAssignmentMessage;
	}



}
