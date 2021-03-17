<?php

namespace app\libraries;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;
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

    public static function initialize(string $secret, string $issuer): void {
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
        $expire_time = ($persistent) ? time() + (7 * 24 * 60 * 60) : 0;
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

    public static function parseSessionToken(string $token): Token {
        $token = self::parseToken($token);
        if (
            !$token->claims()->has('session_id') ||
            !$token->claims()->has('expire_time') ||
            !$token->claims()->has('sub')
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

    private static function parseToken(string $token): Token {
        $token = self::$configuration->parser()->parse($token);

        if (!self::$configuration->validator()->validate($token, ...self::$configuration->validationConstraints())) {
            throw new \InvalidArgumentException("Invalid signature for token");
        }

        $headers = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        foreach ($headers as $key => $value) {
            if ($token->headers()->get($key) !== $value) {
                throw new \InvalidArgumentException("Invalid value for ${key}: ${value}");
            }
        }

        return $token;
    }
}
