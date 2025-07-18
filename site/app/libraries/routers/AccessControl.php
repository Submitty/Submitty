<?php

namespace app\libraries\routers;

use Attribute;

/**
 * Attribute class for #[AccessControl()].
 *
 * The attribute checks if users have access to a class or method.
 *
 * By setting $role, the class or method will only be accessible to users
 * with a higher or equal rank to that role. Roles include INSTRUCTOR,
 * FULL_ACCESS_GRADER, LIMITED_ACCESS_GRADER and STUDENT.
 *
 * By setting $permission, the class or method will only be accessible to
 * users with that permission. Please check app/libraries/Access to get a
 * full list of possible permissions.
 *
 * Example (role only):
 *
 * The following function is only accessible to full access graders and
 * instructors.
 *
 * ```php
 * #[AccessControl(role: "FULL_ACCESS_GRADER")]
 * public function foo() {...}
 * ```
 * Example (permission only):
 *
 * All functions inside the following class is only accessible to users
 * with "grading.simple" permission, which is equivalent to having
 * canI("grading.simple") being true.
 *
 * ```php
 * #[AccessControl(permission: "grading.simple")]
 * class SomeController extends AbstractController {...}
 * ```
 *
 * Example (role & permission):
 *
 * The following function is only accessible to full access graders
 * and instructors with "grading.simple" permission.
 *
 * ```php
 * #[AccessControl(role: "FULL_ACCESS_GRADER", permission: "grading.simple")]
 * public function foo() {...}
 * ```
 *
 * Note that if you use method level #[AccessControl()] attribute, the class
 * level #[AccessControl()] attribute will not be considered at the same time.
 * For clarity, it is recommended not to have class and method level attribute
 * at the same time.
 *
 * Example (class & method):
 *
 * The foo() function will only consider if the user has the "instructor"
 * role, and will NOT check the "grading.simple" permission.
 *
 * ```php
 * #[AccessControl(permission: "grading.simple")]
 * class SomeController extends AbstractController {}
 * ```
 *
 * and then in the class:
 *
 * ```php
 * #[AccessControl(role: "INSTRUCTOR")]
 * public function foo() {...}
 * ```
 */

#[Attribute]
class AccessControl {
    /**
     * @var string|null "INSTRUCTOR", "FULL_ACCESS_GRADER", "LIMITED_
     * ACCESS_GRADER" or "STUDENT".
     */
    private $role;

    /** @var string|null "SUPERUSER"|"FACULTY"|"USER" */
    private $level;

    /** @var string|null */
    private $permission;

    public function __construct(
        string $role = null,
        string $level = null,
        string $permission = null,
    ) {
        if ($role !== null) {
            $this->setRole($role);
        }
        if ($level !== null) {
            $this->setLevel($level);
        }
        if ($permission !== null) {
            $this->setPermission($permission);
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setRole(string $role): void {
        $role = strtoupper($role);
        if (
            in_array(
                $role,
                [
                    "INSTRUCTOR",
                    "FULL_ACCESS_GRADER",
                    "LIMITED_ACCESS_GRADER",
                    "STUDENT"
                ]
            )
        ) {
            $this->role = $role;
        }
        else {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }
    }

    public function getRole(): ?string {
        return $this->role;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setLevel(string $level): void {
        $level = strtoupper($level);
        if (
            in_array(
                $level,
                [
                    "SUPERUSER",
                    "FACULTY",
                    "USER"
                ]
            )
        ) {
            $this->level = $level;
        }
        else {
            throw new \InvalidArgumentException("Invalid level: {$level}");
        }
    }

    public function getLevel(): ?string {
        return $this->level;
    }

    public function setPermission(string $permission): void {
        $this->permission = $permission;
    }

    public function getPermission(): ?string {
        return $this->permission;
    }
}
