<?php

namespace tests\app\libraries;

use app\libraries\TokenManager;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class TokenManagerTester extends \PHPUnit\Framework\TestCase {
    public function testCreateSessionToken() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id',
            'https://submitty.org',
            'secret'
        );
        $this->assertEquals('session_id', $token->getClaim('session_id'));
        $this->assertEquals('user_id', $token->getClaim('sub'));
        $this->assertEquals('https://submitty.org', $token->getClaim('iss'));

        //var_dump((string) $token);
        $new_token = TokenManager::parseSessionToken(
            (string) $token,
            'https://submitty.org',
            'secret'
        );
        $this->assertTrue($new_token->verify(new Sha256(), 'secret'));
        $this->assertEquals('session_id', $token->getClaim('session_id'));
        $this->assertEquals('user_id', $token->getClaim('sub'));
        $this->assertEquals('https://submitty.org', $token->getClaim('iss'));
    }

    public function testWrongSessionTokenSecret() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id',
            'https://submitty.org',
            'secret'
        );

        $this->expectException(\RuntimeException::class);
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid claims in token');
        TokenManager::parseSessionToken((string) $token, 'https://wrong.org', 'secret');
    }

    public function testWrongSessionTokenType() {
        // Generated at https://jwt.io/ with typ AAA
        $token = 'eyJ0eXAiOiJBQUEiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWQiLCJzZXNzaW9uX2lkIjoic2Vzc2lvbl9pZCJ9.H10IvoXjP-Gf2Z6fgT-e8V5TgHkohi48Xlq6lD4rHwg';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid value for typ: JWT');
        TokenManager::parseSessionToken($token, 'https://submitty.org', 'secret');
    }

    public function testWrongSessionTokenUserId() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWRfd3JvbmciLCJleHBpcmVfdGltZSI6ImV4cGlyZV90aW1lIiwic2Vzc2lvbl9pZCI6InNlc3Npb25faWQifQ.dfwuw9OUCkrac7veFkTb9Gy0KkRMIBp6O-vvLNh3y9c';
        $parsed_token = TokenManager::parseSessionToken($token, 'https://submitty.org', 'secret');
        $this->assertNotEquals('user_id', $parsed_token->getClaim('sub'));
    }

    public function testMissingSessionId() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c2VyX2lkIiwiaXNzIjoiaHR0cHM6Ly9zdWJtaXR0eS5vcmciLCJleHBpcmVfdGltZSI6MH0.SDjPG61GUYWf5agRWJVZAd_iuiHHlQceuKeGgCsc1dY';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing claims in session token');
        TokenManager::parseSessionToken($token, 'https://submitty.org', '');
    }

    public function testCreateApiToken() {
        $token = TokenManager::generateApiToken(
            'api_key',
            'https://submitty.org',
            'secret'
        );
        $this->assertEquals('api_key', $token->getClaim('api_key'));
        $this->assertEquals('https://submitty.org', $token->getClaim('iss'));

        //var_dump((string) $token);
        $new_token = TokenManager::parseApiToken(
            (string) $token,
            'https://submitty.org',
            'secret'
        );
        $this->assertTrue($new_token->verify(new Sha256(), 'secret'));
        $this->assertEquals('api_key', $token->getClaim('api_key'));
        $this->assertEquals('https://submitty.org', $token->getClaim('iss'));
    }

    public function testWrongApiTokenSecret() {
        $token = TokenManager::generateApiToken(
            'api_key',
            'https://submitty.org',
            'secret'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseApiToken((string) $token, 'https://submitty.org', '');
    }

    public function testWrongApiTokenIssuer() {
        $token = TokenManager::generateApiToken(
            'api_key',
            'https://submitty.org',
            'secret'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid claims in token');
        TokenManager::parseApiToken((string) $token, 'https://wrong.org', 'secret');
    }

    public function testMissingApiKey() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyJ9.J9gYCSxsWhDg2SQ0ZU1-8vSBagRqfujj1zh3CJ7JGgM';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing claims in api token');
        TokenManager::parseApiToken($token, 'https://submitty.org', 'secret');
    }
}