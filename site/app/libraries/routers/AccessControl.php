<?php

namespace app\libraries\routers;

use Doctrine\Common\Annotations\Annotation;

/**
 * Annotation class for @AccessControl().
 *
 * The annotation checks if users have access to a class or method.
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
 *      The following function is only accessible to full access graders and
 *      instructors.
 *
 *      @AccessControl(role="FULL_ACCESS_GRADER")
 *      public function foo() {...}
 *
 * Example (permission only):
 *
 *      All functions inside the following class is only accessible to users
 *      with "grading.simple" permission, which is equivalent to having
 *      canI("grading.simple") being true.
 *
 *      @AccessControl(permission="grading.simple")
 *      class SomeController extends AbstractController {...}
 *
 * Example (role & permission):
 *
 *      The following function is only accessible to full access graders
 *      and instructors with "grading.simple" permission.
 *
 *      @AccessControl(role="FULL_ACCESS_GRADER", permission="grading.simple")
 *      public function foo() {...}
 *
 * Note that if you use method level @AccessControl() annotation, the class
 * level @AccessControl() annotation will not be considered at the same time.
 * For clarity, it is recommended not to have class and method level annotations
 * at the same time.
 *
 * Example (class & method):
 *
 *      The foo() function will only consider if the user has the "instructor"
 *      role, and will NOT check the "grading.simple" permission.
 *
 *      @AccessControl(permission="grading.simple")
 *      class SomeController extends AbstractController {
 *          @AccessControl(role="INSTRUCTOR")
 *          public function foo() {...}
 *      }
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class AccessControl {
    /**
     * @var string "INSTRUCTOR", "FULL_ACCESS_GRADER", "LIMITED_
     * ACCESS_GRADER" or "STUDENT".
     */
    private $role = null;

    /**
     * @var string
     */
    private $permission = null;

    /**
     * AccessControl constructor.
     * @param array $data
     * @throws \BadMethodCallException
     */
    public function __construct(array $data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf('Unknown property "%s" on annotation "%s".', $key, \get_class($this)));
            }
            $this->$method($value);
        }
    }

    /**
     * @param string $role
     * @throws \InvalidArgumentException
     */
    public function setRole($role) {
        $role = strtoupper($role);
        if (in_array(
            $role,
            [
                "INSTRUCTOR",
                "FULL_ACCESS_GRADER",
                "LIMITED_ACCESS_GRADER",
                "STUDENT"
            ]
        )) {
            $this->role = $role;
        }
        else {
            throw new \InvalidArgumentException;
        }
    }

    /**
     * @return string
     */
    public function getRole() {
        return $this->role;
    }

    /**
     * @param string $permission
     */
    public function setPermission($permission) {
        $this->permission = $permission;
    }

    /**
     * @return string
     */
    public function getPermission() {
        return $this->permission;
    }

}
