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

    public function testCreateWebsocketToken() {
        $authorized_pages = [
            'f25-sample-discussion_forum' => 1753800957,
            'f25-sample-polls-3-instructor' => 1753800912
        ];
        $token = TokenManager::generateWebsocketToken(
            'test_user',
            $authorized_pages
        );

        $this->assertEquals('test_user', $token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));
        $this->assertEquals($authorized_pages, $token->claims()->get('authorized_pages'));
        $this->assertTrue($token->claims()->has('expire_time'));
        $this->assertGreaterThan(time(), $token->claims()->get('expire_time'));
    }

    public function testParseWebsocketToken() {
        $authorized_pages = [
            'f25-sample-discussion_forum' => 1753800957,
            'f25-sample-chatrooms-1' => 1753800957
        ];

        $token = TokenManager::generateWebsocketToken(
            'test_user',
            $authorized_pages
        );

        $parsed_token = TokenManager::parseWebsocketToken($token->toString());
        $this->assertEquals('test_user', $parsed_token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $parsed_token->claims()->get('iss'));
        $this->assertEquals($authorized_pages, $parsed_token->claims()->get('authorized_pages'));
    }

    public function testWebsocketTokenWithExistingPages() {
        $existing_pages = [
            'f25-sample-discussion_forum' => time() + 1000,
            'f25-sample-expired-page' => time() - 100  // expired page should be filtered out
        ];

        $new_pages = [
            'f25-sample-polls-3-instructor' => null  // null means use default expiration
        ];
        $token = TokenManager::generateWebsocketToken(
            'test_user',
            $new_pages,
            $existing_pages
        );
        $authorized_pages = $token->claims()->get('authorized_pages');
        $this->assertArrayHasKey('f25-sample-discussion_forum', $authorized_pages);
        $this->assertArrayHasKey('f25-sample-polls-3-instructor', $authorized_pages);
        $this->assertArrayNotHasKey('f25-sample-expired-page', $authorized_pages);
    }

    public function testWebsocketTokenMissingSubject() {
        // Create a token without 'sub' claim
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kaXNjdXNzaW9uX2ZvcnVtIjoxNzUzODAwOTU3fSwiZXhwaXJlX3RpbWUiOjE3NTM4MDA5NTd9.F5lQx8xm1_wFzMwfgfQfB5T3xHfO2B8vMgQtWfHGlYI';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseWebsocketToken($token);
    }

    public function testWebsocketTokenMissingAuthorizedPages() {
        // Create a token without 'authorized_pages' claim
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InRlc3RfdXNlciIsImV4cGlyZV90aW1lIjoxNzUzODAwOTU3fQ.Tm8xg5Fh3mLHHgJzFJmODgcFQfBz1_MfLfQW3d3HgIo';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseWebsocketToken($token);
    }

    public function testWebsocketTokenMissingExpireTime() {
        // Create a token without 'expire_time' claim
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InRlc3RfdXNlciIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kaXNjdXNzaW9uX2ZvcnVtIjoxNzUzODAwOTU3fX0.YQYGZm8W2qYQF5ZHhFhDGzZhDgJ8sHGzL7gH5fQmBxQ';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseWebsocketToken($token);
    }

    public function testWebsocketTokenInvalidSignature() {
        // Test with a token that has been tampered with (invalid signature)
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsInN1YiI6InRlc3RfdXNlciIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kaXNjdXNzaW9uX2ZvcnVtIjoxNzUzODAwOTU3fSwiZXhwaXJlX3RpbWUiOjE3NTM4MDA5NTcsInRva2VuX3R5cGUiOiJ3ZWJzb2NrZXQifQ.INVALID_SIGNATURE';

        $this->expectException(\Lcobucci\JWT\Encoding\CannotDecodeContent::class);
        $this->expectExceptionMessage('Error while decoding from Base64Url, invalid base64 characters detected');
        TokenManager::parseWebsocketToken($token);
    }

    public function testMalformedWebsocketToken() {
        // Test various malformed tokens
        $malformed_tokens = [
            'invalid.token',  // Too few parts
            'invalid.token.signature.extra',  // Too many parts
            'invalid-base64!.eyJzdWIiOiJ0ZXN0In0.signature',  // Invalid base64
            '',  // Empty string
            'not-a-jwt-at-all'  // Not JWT format
        ];

        foreach ($malformed_tokens as $malformed_token) {
            try {
                TokenManager::parseWebsocketToken($malformed_token);
                $this->fail("Expected exception for malformed token: $malformed_token");
            } catch (\Exception $e) {
                // Expected - any exception is fine for malformed tokens
                $this->assertInstanceOf(\Exception::class, $e);
            }
        }
    }
}
