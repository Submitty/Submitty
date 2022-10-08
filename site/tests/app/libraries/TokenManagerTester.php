<?php

namespace tests\app\libraries;

use app\libraries\TokenManager;

class TokenManagerTester extends \PHPUnit\Framework\TestCase {
    public static function setUpBeforeClass(): void {
        TokenManager::initialize(
            'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijkl',
            'https://submitty.org'
        );
    }

    public function testInvalidSecretLength() {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Invalid secret length, expect at least 64 characters, got 5 characters');
        TokenManager::initialize('short', 'https://submitty.org');
    }

    public function testCreateSessionToken() {
        $token = TokenManager::generateSessionToken(
            'session_id',
            'user_id'
        );
        $this->assertEquals('session_id', $token->claims()->get('session_id'));
        $this->assertEquals('user_id', $token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));

        $new_token = TokenManager::parseSessionToken($token->toString());
        $this->assertEquals('session_id', $new_token->claims()->get('session_id'));
        $this->assertEquals('user_id', $new_token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $new_token->claims()->get('iss'));
    }

    public function testWrongSessionTokenSecret() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWQiLCJleHBpcmVfdGltZSI6IjAiLCJzZXNzaW9uX2lkIjoic2Vzc2lvbl9pZCJ9.kw9cXcl-4DtBhad6FyH-k8IJREcSjwJKUXM3HvsUwo0';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseSessionToken($token);
    }

    public function testWrongSessionTokenIssuer() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3dyb25nLmNvbSIsInN1YiI6InVzZXJfaWQiLCJleHBpcmVfdGltZSI6IjAiLCJzZXNzaW9uX2lkIjoic2Vzc2lvbl9pZCJ9.qmbDz7SMvlPZtNz2_bQAchHwblCrI_MWczI6FuqRnWM';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseSessionToken($token);
    }

    public function testWrongSessionTokenType() {
        // Generated at https://jwt.io/ with typ AAA
        $token = 'eyJ0eXAiOiJBQUEiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWQiLCJzZXNzaW9uX2lkIjoic2Vzc2lvbl9pZCJ9.aQv5epWWCwXqgZaRo1ZN0wkyOQ-XLuCC38TXN3LVMNQ';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for typ: JWT');
        TokenManager::parseSessionToken($token);
    }

    public function testWrongSessionTokenUserId() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InVzZXJfaWRfd3JvbmciLCJleHBpcmVfdGltZSI6ImV4cGlyZV90aW1lIiwic2Vzc2lvbl9pZCI6InNlc3Npb25faWQifQ.ROWlOZ6ELP6SgMVdSMBTPg39k1xwXgqpErc6jvSilw8';
        $parsed_token = TokenManager::parseSessionToken($token);
        $this->assertNotEquals('user_id', $parsed_token->claims()->get('sub'));
    }

    public function testMissingSessionId() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c2VyX2lkIiwiaXNzIjoiaHR0cHM6Ly9zdWJtaXR0eS5vcmciLCJleHBpcmVfdGltZSI6MH0.7T41WXTSy1p3tcVdi6c0rnQvGibLu3w00axoFihT1aY';
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

        $new_token = TokenManager::parseApiToken($token->toString());
        $this->assertEquals('api_key', $new_token->claims()->get('api_key'));
        $this->assertEquals('https://submitty.org', $new_token->claims()->get('iss'));
    }

    public function testWrongApiTokenSecret() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsImFwaV9rZXkiOiJteV9hcGlfa2V5In0.24RtKw-_0wITbSYPMJfTCmfPHzsc2mPFG-z_5IXqOBs';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseApiToken($token, 'https://submitty.org', '');
    }

    public function testWrongApiTokenIssuer() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL3dyb25nLm9yZyIsImFwaV9rZXkiOiJteV9hcGlfa2V5In0.EgCTxwY3LDoGeWY8ftrkhYTW1-MNnRyzMVV6nfBpZFI';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseApiToken($token, 'https://wrong.org', 'secret');
    }

    public function testMissingApiKey() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyJ9.WkIFu5a2kspsLKtXKzTwi64sDnA66anMv6u0DZjaKq8';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing claims in api token');
        TokenManager::parseApiToken($token);
    }
}
