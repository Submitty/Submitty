<?php

namespace app\libraries;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;

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
    public static function generateSessionToken(
        string $session_id,
        string $user_id,
        string $issuer,
        string $secret,
        $persistent=true
    ): Token {
        $expire_time = ($persistent) ? time() + (7 * 24 * 60 * 60) : 0;
        return (new Builder())->issuedBy($issuer)
            ->issuedAt(time())
            ->relatedTo($user_id)
            ->withClaim('session_id', $session_id)
            ->withClaim('expire_time', $expire_time)
            ->getToken(new Sha256(), new Key($secret));
    }

    public static function generateApiToken(
        string $api_key,
        string $issuer,
        string $secret
    ): Token {
        return (new Builder())->issuedBy($issuer)
            ->issuedAt(time())
            ->withClaim('api_key', $api_key)
            ->getToken(new Sha256(), new Key($secret));
    }

    public static function parseSessionToken(string $token, string $issuer, string $secret): Token {
        $token = self::parseToken($token, $issuer, $secret);
        if (!$token->hasClaim('session_id') || !$token->hasClaim('expire_time') || !$token->hasClaim('sub')) {
            throw new \RuntimeException('Missing claims in session token');
        }
        return $token;
    }

    public static function parseApiToken(string $token, string $issuer, string $secret): Token {
        $token = self::parseToken($token, $issuer, $secret);
        if (!$token->hasClaim('api_key')) {
            throw new \RuntimeException('Missing claims in api token');
        }
        return $token;
    }

    private static function parseToken(string $token, string $issuer, string $secret): Token {
        $token = (new Parser())->parse($token);
        if (!$token->verify(new Sha256(), $secret)) {
            throw new \RuntimeException("Invalid signature for token");
        }

        $headers = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        foreach ($headers as $key => $value) {
            if ($token->getHeader($key) !== $value) {
                throw new \RuntimeException("Invalid value for ${key}: ${value}");
            }
        }

        $data = new ValidationData();
        $data->setIssuer($issuer);
        if (!$token->validate($data)) {
            throw new \RuntimeException('Invalid claims in token');
        }

        return $token;
    }
}
