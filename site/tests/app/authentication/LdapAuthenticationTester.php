<?php

declare(strict_types=1);

namespace tests\app\authentication;

use app\authentication\LdapAuthentication;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\models\Config;
use app\models\User;
use PHPUnit\Framework\TestCase;

class LdapAuthenticationTester extends TestCase {
    use \phpmock\phpunit\PHPMock;

    public function testEmptyUserId() {
        $core = new Core();
        $auth = new LdapAuthentication($core);
        $auth->setUserId('');
        $auth->setPassword('');
        $this->assertFalse($auth->authenticate());
    }

    public function testEmptyPassword() {
        $core = new Core();
        $auth = new LdapAuthentication($core);
        $auth->setUserId('instructor');
        $auth->setPassword('');
        $this->assertFalse($auth->authenticate());
    }

    public function testInvalidUser() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->expects($this->once())->method('getSubmittyUser')->with('instructor')->willReturn(null);
        $core->setQueries($queries);
        $auth = new LdapAuthentication($core);
        $auth->setUserId('instructor');
        $auth->setPassword('instructor');
        $this->assertFalse($auth->authenticate());
    }

    public function testPassLdap() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $user = new User($core, [
            'user_id' => 'test',
            'user_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ]);
        $queries->expects($this->once())->method('getSubmittyUser')->with('instructor')->willReturn($user);
        $core->setQueries($queries);
        $config = new Config($core);
        $config->setLdapOptions([
            'url' => 'ldap://localhost',
            'uid' => 'uid',
            'bind_dn' => 'ou=users,dc=vagrant,dc=local'
        ]);
        $core->setConfig($config);

        $auth = new LdapAuthentication($core);
        $auth->setUserId('instructor');
        $auth->setPassword('instructor');

        $ldap_bind = $this->getFunctionMock("app\\authentication", "ldap_bind");
        $ldap_bind
            ->expects($this->once())
            ->willReturn(true);

        $this->assertTrue($auth->authenticate());
    }

    public function testFailLdap() {
        $core = new Core();
        $queries = $this->createMock(DatabaseQueries::class);
        $user = new User($core, [
            'user_id' => 'test',
            'user_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ]);
        $queries->expects($this->once())->method('getSubmittyUser')->with('instructor')->willReturn($user);
        $core->setQueries($queries);
        $config = new Config($core);
        $config->setLdapOptions([
            'url' => 'ldap://localhost',
            'uid' => 'uid',
            'bind_dn' => 'ou=users,dc=vagrant,dc=local'
        ]);
        $core->setConfig($config);

        $auth = new LdapAuthentication($core);
        $auth->setUserId('instructor');
        $auth->setPassword('instructor');

        $ldap_bind = $this->getFunctionMock("app\\authentication", "ldap_bind");
        $ldap_bind
            ->expects($this->once())
            ->willReturn(false);

        $this->assertFalse($auth->authenticate());
    }
}
