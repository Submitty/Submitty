<?php

namespace app\libraries\routers;

use Doctrine\Common\Annotations\Annotation;

/**
 * Annotation class for @AccessControl().
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class AccessControl {
    /**
     * @var string "INSTRUCTOR", "FULL_ACCESS GRADER", "LIMITED_
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
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf('Unknown property "%s" on annotation "%s".', $key, \get_class($this)));
            }
            $this->$method($value);
        }
    }

    /**
     * @param string $role
     */
    public function setRole($role) {
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