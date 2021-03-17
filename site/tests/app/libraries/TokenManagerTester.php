<?php

namespace tests\app\libraries;

use app\libraries\TokenManager;

class TokenManagerTester extends \PHPUnit\Framework\TestCase {
    public static function setUpBeforeClass(): void {
        TokenManager::initialize('secret', 'https://submitty.org');
    }

    public function testCreateSessionToken() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id'
        );
        $this->assertEquals('session_id', $token->claims()->get('session_id'));
        $this->assertEquals('user_id', $token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));

        $new_token = TokenManager::parseSessionToken(
            (string) $token
        );
        $this->assertEquals('session_id', $token->claims()->get('session_id'));
        $this->assertEquals('user_id', $token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));
    }

    /*
    public function testWrongSessionTokenSecret() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseSessionToken((string) $token, 'https://submitty.org', '');
    }

    public function testWrongSessionTokenIssuer() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id',
            'https://submitty.org',
            'secret'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid claims in token');
        TokenManager::parseSessionToken((string) $token, 'https://wrong.org', 'secret');
    }
    */

    public function testWrongSessionTokenType() {
        // Generated at https://jwt.io/ with typ AAA
        $token = 'eyJ0eXAiOiJBQUEiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWQiLCJzZXNzaW9uX2lkIjoic2Vzc2lvbl9pZCJ9.H10IvoXjP-Gf2Z6fgT-e8V5TgHkohi48Xlq6lD4rHwg';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for typ: JWT');
        TokenManager::parseSessionToken($token, 'https://submitty.org', 'secret');
    }

    public function testWrongSessionTokenUserId() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWRfd3JvbmciLCJleHBpcmVfdGltZSI6ImV4cGlyZV90aW1lIiwic2Vzc2lvbl9pZCI6InNlc3Npb25faWQifQ.dfwuw9OUCkrac7veFkTb9Gy0KkRMIBp6O-vvLNh3y9c';
        $parsed_token = TokenManager::parseSessionToken($token, 'https://submitty.org', 'secret');
        $this->assertNotEquals('user_id', $parsed_token->claims()->get('sub'));
    }

    public function testMissingSessionId() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c2VyX2lkIiwiaXNzIjoiaHR0cHM6Ly9zdWJtaXR0eS5vcmciLCJleHBpcmVfdGltZSI6MH0.nEHLivbiQR3WSOpD92bxmWi-K-XiUrBugea7xKKBTiU';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing claims in session token');
        TokenManager::parseSessionToken($token);
    }

    public function testCreateApiToken() {
        $token = TokenManager::generateApiToken(
            'api_key'
        );
        $this->assertEquals('api_key', $token->claims()->get('api_key'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));

        $new_token = TokenManager::parseApiToken(
            (string) $token
        );
        $this->assertEquals('api_key', $token->claims()->get('api_key'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));
    }

    /*
    public function testWrongApiTokenSecret() {
        $token = TokenManager::generateApiToken(
            'api_key',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseApiToken((string) $token, 'https://submitty.org', '');
    }

    public function testWrongApiTokenIssuer() {
        $token = TokenManager::generateApiToken(
            'api_key',
            'https://submitty.org',
            'secret'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid claims in token');
        TokenManager::parseApiToken((string) $token, 'https://wrong.org', 'secret');
    }
    */

    public function testMissingApiKey() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyJ9.J9gYCSxsWhDg2SQ0ZU1-8vSBagRqfujj1zh3CJ7JGgM';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing claims in api token');
        TokenManager::parseApiToken($token);
    }
}
