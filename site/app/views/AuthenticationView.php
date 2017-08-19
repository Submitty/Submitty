<?php

namespace app\views;

class AuthenticationView extends AbstractView {
    public function loginForm() {
        $return = <<<HTML
<div class="content">
    <h2>Submitty Login</h2>
    <div class="sub">
        <form action="{$this->core->getConfig()->getSiteUrl()}" method="post" id="login">
            <input type="hidden" name="component" value="authentication" />
            <input type="hidden" name="page" value="checkLogin" />
HTML;
        if (isset($_REQUEST['old'])) {
            foreach ($_REQUEST['old'] as $key => $value) {
                $return .= "<input type='hidden' name='old_{$key}' value='{$value}' />\n";
            }
        }
        $return .= <<<HTML
            <input type="text" name="user_id" placeholder="User ID" /><br />
            <input type="password" name="password" placeholder="Password" /><br />
            <label for="stay_logged_in">Stay Logged In</label> <input type="checkbox" name="stay_logged_in" checked /><br />
            <input type="submit" name="login" value="Login" class="btn btn-default" />
        </form>
    </div>
</div>
HTML;
        return $return;
    }
}