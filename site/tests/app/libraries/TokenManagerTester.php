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
        $current_time = time();
        // Ensure the issued time is now +/- 20 seconds to account for clock skew
        $min_issued_time = $current_time - 10;
        $max_issued_time = $min_issued_time + 20;
        // Ensure the expiration time is 30 minutes from now +/- 20 seconds to account for clock skew
        $min_expired_time = $current_time + 1790;
        $max_expired_time = $min_expired_time + 30;

        $authorized_page = 'f25-sample-defaults';
        $token = TokenManager::generateWebsocketToken(
            'test_user',
            'session_id',
            $authorized_page
        );

        $this->assertEquals('test_user', $token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $token->claims()->get('iss'));
        $this->assertEquals('session_id', $token->claims()->get('session_id'));
        $this->assertCount(1, $token->claims()->get('authorized_pages'));
        $this->assertEquals($authorized_page, array_keys($token->claims()->get('authorized_pages'))[0]);

        $this->assertGreaterThan($min_issued_time, $token->claims()->get('iat')->getTimestamp());
        $this->assertLessThan($max_issued_time, $token->claims()->get('iat')->getTimestamp());

        $this->assertGreaterThan($min_expired_time, $token->claims()->get('authorized_pages')[$authorized_page]);
        $this->assertLessThan($max_expired_time, $token->claims()->get('authorized_pages')[$authorized_page]);

        $this->assertTrue($token->claims()->has('expire_time'));
        $this->assertGreaterThan($min_expired_time, $token->claims()->get('expire_time'));
        $this->assertLessThan($max_expired_time, $token->claims()->get('expire_time'));
    }

    public function testParseWebsocketToken() {
        $future_time = time() + 1000;
        $expired_time = time() - 1000;
        $existing_authorized_pages = [
            'f25-sample-defaults' => $future_time,
            'f25-sample-chatrooms-1' => $future_time,
            'f25-sample-chatrooms-2' => $expired_time
        ];
        $authorized_page = 'f25-tutorial-defaults';

        $token = TokenManager::generateWebsocketToken(
            'test_user',
            'session_id',
            $authorized_page,
            $existing_authorized_pages
        );

        $parsed_token = TokenManager::parseWebsocketToken($token->toString());
        $this->assertEquals('test_user', $parsed_token->claims()->get('sub'));
        $this->assertEquals('https://submitty.org', $parsed_token->claims()->get('iss'));
        $this->assertEquals('session_id', $parsed_token->claims()->get('session_id'));
        $this->assertCount(3, $parsed_token->claims()->get('authorized_pages'));
        // Expired pages should be removed
        $this->assertArrayNotHasKey('f25-sample-chatrooms-2', $parsed_token->claims()->get('authorized_pages'));
        // Old pages should persist with the same expiration time
        $this->assertEquals($future_time, $parsed_token->claims()->get('authorized_pages')['f25-sample-defaults']);
        $this->assertEquals($future_time, $parsed_token->claims()->get('authorized_pages')['f25-sample-chatrooms-1']);
        // New authorized page should have a future expiration time
        $this->assertGreaterThan($future_time, $parsed_token->claims()->get('authorized_pages')['f25-tutorial-defaults']);
    }

    public function testWebsocketTokenMissingSubject() {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3N1Ym1pdHR5Lm9yZyIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kaXNjdXNzaW9uX2ZvcnVtIjoxNzUzODAwOTU3fSwiZXhwaXJlX3RpbWUiOjE3NTM4MDA5NTd9.F5lQx8xm1_wFzMwfgfQfB5T3xHfO2B8vMgQtWfHGlYI';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseWebsocketToken($token);
    }

    public function testWebsocketTokenMissingClaims() {
        $missing = [
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NTM3OTczNTcuNTA0NjMxLCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjE1MTEvIiwic3ViIjoiaW5zdHJ1Y3RvciIsInNlc3Npb25faWQiOiJjNWM2YjRlODFjMmUxM2UzM2M4MjhlYjhiODFkNjZkMiIsImV4cGlyZV90aW1lIjoxNzUzODAwOTU3fQ.cYK3mmRAnstXNeClfjsIZhwsoyMhFO55zv9RextmV_U', // Missing authorized_pages
            // 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NTM3OTczNTcuNTA0NjMxLCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjE1MTEvIiwic3ViIjoiaW5zdHJ1Y3RvciIsInNlc3Npb25faWQiOiJjNWM2YjRlODFjMmUxM2UzM2M4MjhlYjhiODFkNjZkMiIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kZWZhdWx0cyI6MTc1MzgwMDk1N319.FJiVL8q2gKiua8-UZkvriV-PZNcs2PP7aeOcjRaXw4Q', // Missing expire_time
            // 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NTM3OTczNTcuNTA0NjMxLCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjE1MTEvIiwic3ViIjoiaW5zdHJ1Y3RvciIsImF1dGhvcml6ZWRfcGFnZXMiOnsiZjI1LXNhbXBsZS1kZWZhdWx0cyI6MTc1MzgwMDk1N30sImV4cGlyZV90aW1lIjoxNzUzODAwOTU3fQ.DhDL89-7PNd9UA6gGWsF3h71f2fUFW1_n4nOAfEB3M8', // Missing session_id
            // 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NTM3OTczNTcuNTA0NjMxLCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjE1MTEvIiwic2Vzc2lvbl9pZCI6ImM1YzZiNGU4MWMyZTEzZTMzYzgyOGViOGI4MWQ2NmQyIiwiYXV0aG9yaXplZF9wYWdlcyI6eyJmMjUtc2FtcGxlLWRlZmF1bHRzIjoxNzUzODAwOTU3fSwiZXhwaXJlX3RpbWUiOjE3NTM4MDA5NTd9.Gv6-SLfXEUJ3meme98bI31Yn5aokXLYcBV_iHjq4vK0', // Missing sub
        ];
        foreach ($missing as $token) {
            $this->expectException(\InvalidArgumentException::class);
            // TODO: fix this
            // $this->expectExceptionMessage("Missing claims in websocket token");
            $this->expectExceptionMessage("Invalid signature for token");
            TokenManager::parseWebsocketToken($token);
        }
    }

    public function testWebsocketTokenInvalidSignature() {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NTM3OTczNTcuNTA0NjMxLCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjE1MTEvIiwic3ViIjoiaW5zdHJ1Y3RvciIsInNlc3Npb25faWQiOiJjNWM2YjRlODFjMmUxM2UzM2M4MjhlYjhiODFkNjZkMiIsImV4cGlyZV90aW1lIjoxNzUzODAwOTU3fQ.HPMVrx8Ceh8zwDfo7K--7rSIBAm48X67mrou4AFUqPk';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature for token');
        TokenManager::parseWebsocketToken($token);
    }
}
