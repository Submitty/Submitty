<?php

declare(strict_types=1);

namespace tests\app\controllers\admin;

use app\controllers\admin\EmailRoomSeatingController;
use app\libraries\database\DatabaseQueries;
use app\libraries\response\WebResponse;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\NotificationFactory;
use app\libraries\response\RedirectResponse;
use app\libraries\Utils;
use app\models\Config;
use app\models\Email;
use app\views\admin\EmailRoomSeatingView;

class EmailRoomSeatingControllerTester extends \PHPUnit\Framework\TestCase {
    public function testRenderEmailTemplate(): void {
        $controller = new EmailRoomSeatingController(new Core());
        $response = $controller->renderEmailTemplate();
        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertSame(EmailRoomSeatingView::class, $response->view_class);
        $this->assertSame('displayPage', $response->view_function);
        $this->assertSame([
            EmailRoomSeatingController::DEFAULT_EMAIL_SUBJECT,
            EmailRoomSeatingController::DEFAULT_EMAIL_BODY
        ], $response->parameters);
    }

    public function testEmailSeatingAssignments(): void {
        $_POST["room_seating_email_subject"] = EmailRoomSeatingController::DEFAULT_EMAIL_SUBJECT;
        $_POST["room_seating_email_body"] = EmailRoomSeatingController::DEFAULT_EMAIL_BODY;
        /** @var DatabaseQueries&\PHPUnit\Framework\MockObject\MockObject $queries */
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getEmailListWithIds')->willReturn([
            ['user_id' => 'user_1'],
            ['user_id' => 'user_2'],
            ['user_id' => 'user_3'],
            ['user_id' => 'user_4'],
        ]);
        $core = new Core();
        $core->setQueries($queries);
        $config = new Config($core);
        $config->setSemester('s20');
        $config->setCourse('csci1100');
        $config->setRoomSeatingGradeableId('test');
        $core->setConfig($config);
        /** @var NotificationFactory&\PHPUnit\Framework\MockObject\MockObject $factory */
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->once())->method('sendEmails')->with($this->equalTo([
            new Email($core, [
                "subject" => "Seating Assignment for test",
                "body" => 'Hello,

Listed below is your seating assignment for the upcoming exam test on SEE INSTRUCTOR at SEE INSTRUCTOR.

Location: SEE INSTRUCTOR
Exam Room: SEE INSTRUCTOR
Zone: SEE INSTRUCTOR
Row: SEE INSTRUCTOR
Seat: SEE INSTRUCTOR

Please email your instructor with any questions or concerns.',
                "to_user_id" => 'user_1'
            ]),
            new Email($core, [
                "subject" => "Seating Assignment for test",
                "body" => 'Hello,

Listed below is your seating assignment for the upcoming exam test on 02/02/2020 at 08:00PM EST.

Location: DCC
Exam Room: 318
Zone: SEE INSTRUCTOR
Row: SEE INSTRUCTOR
Seat: SEE INSTRUCTOR

Please email your instructor with any questions or concerns.',
                "to_user_id" => 'user_3'
            ]),
            new Email($core, [
                "subject" => "Seating Assignment for test",
                "body" => 'Hello,

Listed below is your seating assignment for the upcoming exam test on 02/02/2020 at 08:00PM EST.

Location: DCC
Exam Room: 318
Zone: 1
Row: L
Seat: 36

Please email your instructor with any questions or concerns.',
                "to_user_id" => 'user_4'
            ]),
        ]));
        $core->setNotificationFactory($factory);

        $temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());

        try {
            FileUtils::createDir(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test'), true);
            file_put_contents(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test', 'user_1.json'), '{"gradeable": "test"}');
            file_put_contents(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test', 'user_3.json'), json_encode([
                'gradeable' => 'test',
                'date' => '02/02/2020',
                'time' => '08:00PM EST',
                'building' => 'DCC',
                'room' => '318',
            ]));
            file_put_contents(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test', 'user_4.json'), json_encode([
                'gradeable' => 'test',
                'date' => '02/02/2020',
                'time' => '08:00PM EST',
                'building' => 'DCC',
                'room' => '318',
                'zone' => '1',
                'row' => 'L',
                'seat' => 36,
            ]));
            $config->setCoursePath($temp_dir);
            $controller = new EmailRoomSeatingController($core);
            $response = $controller->emailSeatingAssignments();
            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame($core->buildCourseUrl(), $response->url);
        }
        finally {
            if (file_exists($temp_dir)) {
                FileUtils::recursiveRmdir($temp_dir);
            }
        }
    }

    public function testNonDefaultSubjectBody() {
        $_POST["room_seating_email_subject"] = 'Seating Assignment for {$gradeable_id}';
        $_POST["room_seating_email_body"] = 'Blah {$gradeable_id} on {$exam_date} at {$exam_time} in {$exam_building} for {$course_name}.';
        /** @var DatabaseQueries&\PHPUnit\Framework\MockObject\MockObject $queries */
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getEmailListWithIds')->willReturn([['user_id' => 'user_1'], ['user_id' => 'user_2'], ['user_id' => 'user_3']]);
        $core = new Core();
        $core->setQueries($queries);
        $config = new Config($core);
        $config->setSemester('s20');
        $config->setCourse('csci1100');
        $config->setRoomSeatingGradeableId('test');
        $core->setConfig($config);
        /** @var NotificationFactory&\PHPUnit\Framework\MockObject\MockObject $factory */
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->once())->method('sendEmails')->with($this->equalTo([
            new Email($core, [
                "subject" => "Seating Assignment for SEE INSTRUCTOR",
                "body" => 'Blah SEE INSTRUCTOR on SEE INSTRUCTOR at SEE INSTRUCTOR in SEE INSTRUCTOR for csci1100.',
                "to_user_id" => 'user_1'
            ]),
            new Email($core, [
                "subject" => "Seating Assignment for test",
                "body" => 'Blah test on 02/02/2020 at 08:00PM EST in DCC for csci1100.',
                "to_user_id" => 'user_3'
            ]),
        ]));
        $core->setNotificationFactory($factory);

        $temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());

        try {
            FileUtils::createDir(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test'), true);
            file_put_contents(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test', 'user_1.json'), '{}');
            file_put_contents(FileUtils::joinPaths($temp_dir, 'reports', 'seating', 'test', 'user_3.json'), json_encode([
                'gradeable' => 'test',
                'date' => '02/02/2020',
                'time' => '08:00PM EST',
                'building' => 'DCC',
                'room' => '318',
            ]));
            $config->setCoursePath($temp_dir);
            $controller = new EmailRoomSeatingController($core);
            $response = $controller->emailSeatingAssignments();
            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame($core->buildCourseUrl(), $response->url);
        }
        finally {
            if (file_exists($temp_dir)) {
                FileUtils::recursiveRmdir($temp_dir);
            }
        }
    }
}
