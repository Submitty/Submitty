<?php

namespace app\authentication;

use app\exceptions\AuthenticationException;
use app\exceptions\CurlException;
use app\libraries\FileUtils;

/**
 * Class LdapAuthentication
 *
 */
class LdapAuthentication extends AbstractAuthentication {
    public function authenticate(): bool {
        // Check for $this->user_id and $this->password to be non empty
        if (empty($this->user_id) || empty($this->password) ||
            $this->core->getQueries()->getSubmittyUser($this->user_id) === null) {
            return false;
        }

        $settings = $this->core->getConfig()->getAuthenticationSettings()['ldap'];

        $ldap = ldap_connect($settings['url']);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        return @ldap_bind($ldap, "{$settings['uid']}={$this->username},{$settings['bind_dn']}", $this->password);
    }
}
