<?php

namespace app\views;

use app\libraries\Core;

class AuthenticationView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function loginForm() {
        $return = <<<HTML
<div class="content">
    <h2>Login for {$this->core->getFullCourseName()}</h2>
    <div class="sub">
        <form action="{$this->core->getConfig()->getSiteUrl()}" method="post" id="login">
            <input type="hidden" name="component" value="authentication" />
            <input type="hidden" name="page" value="checkLogin" />
HTML;
        foreach ($this->core->getControllerTypes() as $type) {
            if (isset($_REQUEST['old'][$type])) {
                $return .= "<input type='hidden' name='old_{$type}' value='{$_REQUEST['old'][$type]}' />\n";
            }
        }
        $return .= <<<HTML
            <input type="text" name="user_id" placeholder="User ID" /><br />
            <input type="password" name="password" placeholder="Password" /><br />
            <input type="submit" value="Login" class="btn btn-default" />
        </form>
    </div>
</div>
HTML;
        return $return;
    }
}