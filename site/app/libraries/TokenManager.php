<?php

namespace app\libraries;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class TokenManager {
    public static function generateSessionToken(
        string $session_id,
        string $user_id,
        string $issuer,
        string $secret,
        $persistent=true
    ): Token {
        $expire_time = ($persistent) ? time() + (7 * 24 * 60 * 60) : 0;
        return (new Builder())->setIssuer($issuer)
            ->setIssuedAt(time())
            ->setSubject($user_id)
            ->set('session_id', $session_id)
            ->set('expire_time', $expire_time)
            ->sign(new Sha256(), $secret)
            ->getToken();
    }

    public static function getTokenFromSessionCookie(string $token, string $issuer, string $secret): Token {
        $token = (new Parser())->parse($token);
        if (!$token->verify(new Sha256(), $secret)) {
            throw new \RuntimeException("Invalid secret for token");
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

        $claims = $token->getClaims();
        if (!$token->hasClaim('session_id') || !$token->hasClaim('expire_time')) {
            throw new \RuntimeException('Missing claims in session token');
        }
        return $token;
    }
}