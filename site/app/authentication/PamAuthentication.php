<?php

namespace app\authentication;

/**
 * Class PamAuthentication
 *
 * Module that utilizes PAM (@link http://www.linux-pam.org/) to handle authentication.
 * Unfortunately, the PHP-PAM package (@link https://pecl.php.net/package/PAM) is depreciated
 * so to do this requires using a cgi script (@see cgi-bin/pam_check.cgi) that runs python to
 * use its supported PAM module. We save the username/password to a tmp file, pass the random
 * filename to the cgi script via GET, then saves the results to another tmp file passing back
 * the filename via GET to this page, all using the cURL library.
 */
class PamAuthentication implements IAuthentication {
    public function __construct() {
    }

    public function authenticate($username, $password) {
        // authenticate against PAM
        return true;
    }
}