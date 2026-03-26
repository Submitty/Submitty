<?php

namespace app\services;

use app\libraries\Core;

class LoginThrottleService {
    private const MAX_FAILURES = 5;
    private const WINDOW_SECONDS = 900;
    private const LOCKOUT_SECONDS = 900;

    private Core $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function isLocked(string $userId): bool {
        $failures = $this->core->getQueries()->getRecentFailedLoginAttempts($userId, self::WINDOW_SECONDS);
        return $failures >= self::MAX_FAILURES;
    }

    public function getRemainingLockoutSeconds(string $userId): int {
        $earliest = $this->core->getQueries()->getEarliestRecentFailureTimestamp($userId, self::WINDOW_SECONDS);
        if ($earliest === null) {
            return 0;
        }
        $lockoutEnds = strtotime($earliest) + self::LOCKOUT_SECONDS;
        $remaining = $lockoutEnds - time();
        return max(0, $remaining);
    }

    public function recordAttempt(string $userId, string $ip, bool $success): void {
        $this->core->getQueries()->insertLoginAttempt($userId, $ip, $success);
        if ($success) {
            $this->core->getQueries()->clearLoginAttempts($userId);
        }
    }
}
