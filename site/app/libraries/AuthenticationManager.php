<?php

namespace app\libraries;

class AuthenticationManager {
    private $settings;
    /** @var AbstractAuthentication[] */
    private $methods = [];

    private $user_id;
    private $password;

    public function __constructor(Core $core) {
        $this->settings = $core->getConfig()->getAuthenticationSettings();
        foreach ($this->settings['methods'] as $method) {
            $auth_class = "\\app\\authentication\\".$method;
            if (!is_subclass_of($auth_class, 'app\authentication\AbstractAuthentication')) {
                throw new \Exception("Invalid method specified for Authentication. All method should implement the AbstractAuthentication interface.");
            }
            $this->methods[] = new $auth_class($core, $this);
        }
    }

    public function authenticate(): bool {
        foreach ($this->methods as $method) {
            $method->setUserId($this->user_id);
            $method->setPassword($this->password);
            if ($method->authenticate()) {
                return true;
            }
        }
        return false;
    }

    public function setUserId(string $user_id): void {
        $this->user_id = trim(strtolower($user_id));
    }

    public function setPassword(string $password): void {
        $this->password = $password;
    }

    public function getUserId(): string {
        return $this->user_id;
    }
}
