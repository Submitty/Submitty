<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\Config;
use app\models\Email;
use app\models\User;

class EmailTester extends \PHPUnit\Framework\TestCase {
    private string $footer = "\n\n--\nNOTE: This is an automated email notification, which is unable to receive replies.\nPlease refer to the course syllabus for contact information for your teaching staff.";
    /** @var Core */
    private $core;

    public function setUp(): void {
        $this->core = new Core();
        $config = new Config($this->core);
        $config->setCourse('csci1100');
        $config->setBaseUrl('http://localhost');
        $config->setTerm('f21');
        $user = new User($this->core, [
            'user_id' => 'test',
            'user_givenname' => 'Tester',
            'user_preferred_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ]);
        $this->core->setUser($user);
        $this->core->setConfig($config);
    }

    public function tearDown(): void {
        unset($_POST['Anon']);
    }

    public function testBasic(): void {
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'some email',
            'body' => 'email body',
        ]);

        self::assertSame('person', $email->getUserId());
        self::assertSame('[Submitty csci1100]: some email', $email->getSubject());
        $body = $email->getBody();
        self::assertStringContainsString('email body', $body);
        self::assertStringContainsString('NOTE: This is an automated email notification', $body);
        self::assertStringContainsString('Update your email notification settings for this course here:', $body);
        self::assertStringContainsString('http://localhost/courses/f21/csci1100/notifications/settings', $body);
    }

    public function testRelevantUrl(): void {
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'some email',
            'body' => "email body",
            'relevant_url' => 'http://example.com'
        ]);

        self::assertSame('person', $email->getUserId());
        self::assertSame('[Submitty csci1100]: some email', $email->getSubject());
        self::assertSame(
            "email body\n\nClick here for more info: http://example.com" . $this->footer . "\nUpdate your email notification settings for this course here:\nhttp://localhost/courses/f21/csci1100/notifications/settings",
            $email->getBody()
        );
    }

    public function testAuthor(): void {
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'some email',
            'body' => "email body",
            'author' => true
        ]);

        self::assertSame('person', $email->getUserId());
        self::assertSame('[Submitty csci1100]: some email', $email->getSubject());
        self::assertSame(
            "email body\n\nAuthor: Test P." . $this->footer . "\nUpdate your email notification settings for this course here:\nhttp://localhost/courses/f21/csci1100/notifications/settings",
            $email->getBody()
        );
    }

    public function testAuthorAnonymous(): void {
        $_POST['Anon'] = 'Anon';
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'some email',
            'body' => "email body",
            'author' => true
        ]);

        self::assertSame('person', $email->getUserId());
        self::assertSame('[Submitty csci1100]: some email', $email->getSubject());
        self::assertSame(
            "email body" . $this->footer . "\nUpdate your email notification settings for this course here:\nhttp://localhost/courses/f21/csci1100/notifications/settings",
            $email->getBody()
        );
    }

    public function testAllDetails(): void {
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'some email',
            'body' => "email body",
            'relevant_url' => 'http://example.com',
            'author' => true
        ]);

        self::assertSame('person', $email->getUserId());
        self::assertSame('[Submitty csci1100]: some email', $email->getSubject());
        self::assertSame(
            "email body\n\nAuthor: Test P.\nClick here for more info: http://example.com" . $this->footer . "\nUpdate your email notification settings for this course here:\nhttp://localhost/courses/f21/csci1100/notifications/settings",
            $email->getBody()
        );
    }

    public function testNotificationSettingsLink(): void {
        $email = new Email($this->core, [
            'to_user_id' => 'person',
            'subject' => 'test email',
            'body' => 'test body',
        ]);

        $base_url = $this->core->getConfig()->getBaseUrl();
        $course = $this->core->getConfig()->getCourse();
        $term = $this->core->getConfig()->getTerm();
        $notifications_url = $base_url . "/courses/{$term}/{$course}/notifications/settings";

        $body = $email->getBody();
        self::assertStringContainsString("Update your email notification settings for this course here:", $body);
        self::assertStringContainsString($notifications_url, $body);
    }
}
