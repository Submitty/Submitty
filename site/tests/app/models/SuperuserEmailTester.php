<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\Config;
use app\models\SuperuserEmail;
use app\models\User;

class SuperuserEmailTester extends \PHPUnit\Framework\TestCase {
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

    public function testSubjectIsPrefixed(): void {
        $email = new SuperuserEmail($this->core, [
            'to_user_id' => 'person',
            'subject' => 'System Maintenance',
            'body' => 'The system will be down tonight.',
        ]);

        $this->assertSame('[Submitty Admin Announcement]: System Maintenance', $email->getSubject());
        $this->assertSame('person', $email->getUserId());
        $this->assertSame('The system will be down tonight.', $email->getBody());
    }

    public function testSubjectPrefixedWithEmptySubject(): void {
        $email = new SuperuserEmail($this->core, [
            'to_user_id' => 'person',
            'subject' => '',
            'body' => 'body text',
        ]);

        $this->assertSame('[Submitty Admin Announcement]: ', $email->getSubject());
    }
}
