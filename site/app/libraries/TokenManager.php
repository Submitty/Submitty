<?php

namespace app\libraries;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken as Token;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

/**
 * Utility class that wraps around the Lcobucci\JWT library, so that we
 * only define calling it here instead of multiple places. The JWT
 * interface itself allows us to create tokens to use for various
 * parts of the system (like authentication). Minimally, all tokens
 * that are generated should be signed (and their signature then verified)
 * to ensure the tokens have not been hampered with.
 *
 * @see https://jwt.io
 * @see https://github.com/lcobucci/jwt
 */
class TokenManager {
    /** @var Configuration */
    private static $configuration;
    /** @var string */
    private static $issuer;
    /** @var string */
    private const WEBSOCKET_TOKEN_EXPIRATION = "5 minutes";

    public static function initialize(string $secret, string $issuer): void {
        if (mb_strlen($secret) < 64) {
            throw new \LengthException('Invalid secret length, expect at least 64 characters, got ' . mb_strlen($secret) . ' characters');
        }
        self::$configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secret)
        );
        self::$issuer = $issuer;
        self::$configuration->setValidationConstraints(
            new IssuedBy($issuer),
            new SignedWith(
                self::$configuration->signer(),
                self::$configuration->signingKey()
            )
        );
    }

    public static function generateSessionToken(
        string $session_id,
        string $user_id,
        $persistent = true
    ): Token {
        $expire_time = $persistent ? (new \DateTime())->add(\DateInterval::createFromDateString(SessionManager::SESSION_EXPIRATION))->getTimestamp() : 0;
        return self::$configuration->builder()
            ->issuedAt(new \DateTimeImmutable())
            ->issuedBy(self::$issuer)
            ->relatedTo($user_id)
            ->withClaim('session_id', $session_id)
            ->withClaim('expire_time', $expire_time)
            ->getToken(
                self::$configuration->signer(),
                self::$configuration->signingKey()
            );
    }

    public static function generateApiToken(string $api_key): Token {
        return self::$configuration->builder()
            ->issuedAt(new \DateTimeImmutable())
            ->issuedBy(self::$issuer)
            ->withClaim('api_key', $api_key)
            ->getToken(
                self::$configuration->signer(),
                self::$configuration->signingKey()
            );
    }

    public static function generateWebSocketToken(
        string $user_id,
        string $page,
        ?array $existing_authorized_pages = []
    ): Token {
        $token_expire_time = (new \DateTime())->add(\DateInterval::createFromDateString(self::WEBSOCKET_TOKEN_EXPIRATION))->getTimestamp();

        // Persist existing non-expired authorized pages
        $authorized_pages = [];
        foreach ($existing_authorized_pages as $page_identifier => $expire_time) {
            if ($expire_time !== null && $expire_time > time() && $page_identifier !== $page) {
                $authorized_pages[$page_identifier] = $expire_time;
            }
        }

        // Persist new authorized pages with the latest expiration time
        $authorized_pages[$page] = $token_expire_time;

        return self::$configuration->builder()
            ->issuedAt(new \DateTimeImmutable())
            ->issuedBy(self::$issuer)
            ->relatedTo($user_id)
            ->withClaim('authorized_pages', $authorized_pages)
            ->withClaim('expire_time', $token_expire_time)
            ->getToken(
                self::$configuration->signer(),
                self::$configuration->signingKey()
            );
    }

    public static function parseSessionToken(string $token): Token {
        $token = self::parseToken($token);
        if (
            !$token->claims()->has('session_id')
            || !$token->claims()->has('expire_time')
            || !$token->claims()->has('sub')
        ) {
            throw new \InvalidArgumentException('Missing claims in session token');
        }
        return $token;
    }

    public static function parseApiToken(string $token): Token {
        $token = self::parseToken($token);
        if (!$token->claims()->has('api_key')) {
            throw new \InvalidArgumentException('Missing claims in api token');
        }
        return $token;
    }

    public static function parseWebSocketToken(string $token): Token {
        $token = self::parseToken($token);
        if (
            !$token->claims()->has('sub')
            || !$token->claims()->has('authorized_pages')
            || !$token->claims()->has('expire_time')
            || !is_int($token->claims()->get('expire_time'))
        ) {
            throw new \InvalidArgumentException('Missing or invalid claims in WebSocket token');
        }

        // Verify the token expiration time is in the future
        $expire_time = $token->claims()->get('expire_time');
        if (time() > $expire_time) {
            throw new \InvalidArgumentException('WebSocket token has expired');
        }

        // Verify the authorized pages are in the correct format
        $authorized_pages = $token->claims()->get('authorized_pages');
        foreach ($authorized_pages as $key => $value) {
            if (!is_string($key) || !is_int($value)) {
                throw new \InvalidArgumentException('\'authorized_pages\' must be an array in form key: string, value: int');
            }
        }

        return $token;
    }

    private static function parseToken(string $jwt): Token {
        $token = self::$configuration->parser()->parse($jwt);

        // Narrow the type from an abstract token to concrete Plain token type
        if (!$token instanceof Token) {
            throw new \InvalidArgumentException("Invalid token type: " . get_class($token));
        }

        if (!self::$configuration->validator()->validate($token, ...self::$configuration->validationConstraints())) {
            throw new \InvalidArgumentException("Invalid signature for token");
        }

        $headers = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        foreach ($headers as $key => $value) {
            if ($token->headers()->get($key) !== $value) {
                throw new \InvalidArgumentException("Invalid value for {$key}: {$value}");
            }
        }

        return $token;
    }
}
