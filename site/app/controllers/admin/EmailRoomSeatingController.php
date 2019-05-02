<?php

namespace app\controllers\admin;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\FileUtils;
use app\models\Email;


class EmailRoomSeatingController extends AbstractController {
    const DEFAULT_EMAIL_SUBJECT = 'Seating Assignment for {$gradeable_id}';
    const DEFAULT_EMAIL_BODY =
'Hello,

Listed below is your seating assignment for the upcoming exam {$gradeable_id} on {$exam_date} at {$exam_time}.

Location: {$exam_building}
Exam Room: {$exam_room}
Zone: {$exam_zone}
Row: {$exam_row}
Seat: {$exam_seat}

Please email your instructor with any questions or concerns.';

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {

        switch($_REQUEST['action']) {
		        case 'send_email':
                    $this->emailSeatingAssignments();
                    break;
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

        $gradeable_id = $this->core->getConfig()->getRoomSeatingGradeableId();
        $course =  $this->core->getConfig()->getCourse();
        $seating_assignments_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", "seating", $gradeable_id);

        $classList = $this->core->getQueries()->getClassEmailListWithIds();

        foreach($classList as $user) {
            $user_id = $user['user_id'];
            $user_email = $user['user_email'];

            $room_seating_file = FileUtils::joinPaths($seating_assignments_path, "$user_id.json");
            $room_seating_json = FileUtils::readJsonFile($room_seating_file);

            if($room_seating_json === false){
                continue;
            }

            $email_data = [
                "subject" => $this->replacePlaceholders($seating_assignment_subject, $room_seating_json),
                "body" => $this->replacePlaceholders($seating_assignment_body, $room_seating_json),
                "recipient" => $user_email
            ];

            $seating_assignment_email = new Email($this->core, $email_data);
            $this->core->getQueries()->createEmail($seating_assignment_email);
        }

        $this->core->addSuccessMessage("Seating assignments have been sucessfully emailed!");
        return $this->core->redirect($this->core->buildUrl());
    }

    private function replacePlaceholders($message, $data) {
        $replaces = [
            'gradeable' => 'gradeable_id',
            'date' => 'exam_date',
            'time' => 'exam_time',
            'building' => 'exam_building',
            'room' => 'exam_room',
            'zone' => 'exam_zone',
            'row' => 'exam_row',
            'seat' => 'exam_seat',
        ];

        foreach($replaces as $key => $variable) {
        	if(isset($data[$key])) {
        		$message = str_replace('{$' . $variable . '}', $data[$key], $message);
        	}
        }

		$message = str_replace('{$course_name}', $this->core->getConfig()->getCourse(), $message);
        return $message;
    }

}
