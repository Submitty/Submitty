<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\response\RedirectResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\models\Email;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class EmailRoomSeatingController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class EmailRoomSeatingController extends AbstractController {
    const DEFAULT_EMAIL_SUBJECT = 'Seating Assignment for {$gradeable_id}';
    const DEFAULT_EMAIL_BODY = 'Hello,

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

    /**
     * @Route("/{_semester}/{_course}/email_room_seating")
     * @return MultiResponse
     */
    public function renderEmailTemplate() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'EmailRoomSeating'],
                'displayPage',
                EmailRoomSeatingController::DEFAULT_EMAIL_SUBJECT,
                EmailRoomSeatingController::DEFAULT_EMAIL_BODY
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/email_room_seating/send", methods={"POST"})
     * @return MultiResponse
     */
    public function emailSeatingAssignments() {
        $seating_assignment_subject = $_POST["room_seating_email_subject"];
        $seating_assignment_body = $_POST["room_seating_email_body"];

        $gradeable_id = $this->core->getConfig()->getRoomSeatingGradeableId();
        $seating_assignments_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", "seating", $gradeable_id);

        $class_list = $this->core->getQueries()->getEmailListWithIds();

        foreach ($class_list as $user) {
            $user_id = $user['user_id'];

            $room_seating_file = FileUtils::joinPaths($seating_assignments_path, "$user_id.json");
            $room_seating_json = FileUtils::readJsonFile($room_seating_file);

            if ($room_seating_json === false) {
                continue;
            }

            $email_data = [
                "subject" => $this->replacePlaceholders($seating_assignment_subject, $room_seating_json),
                "body" => $this->replacePlaceholders($seating_assignment_body, $room_seating_json),
                "to_user_id" => $user_id
            ];

            $seating_assignment_emails[] = new Email($this->core, $email_data);
        }
        $this->core->getNotificationFactory()->sendEmails($seating_assignment_emails);
        $this->core->addSuccessMessage("Seating assignments have been sucessfully emailed!");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl())
        );
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

        foreach ($replaces as $key => $variable) {
            $message = str_replace('{$' . $variable . '}', $data[$key] ?? 'SEE INSTRUCTOR', $message);
        }

        $message = str_replace('{$course_name}', $this->core->getConfig()->getCourse(), $message);

        return $message;
    }
}
