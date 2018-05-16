<?php

namespace app\authentication;
use app\exceptions\AuthenticationException;
use app\libraries\Core;
use app\libraries\FileUtils;

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
class PamAuthentication extends AbstractAuthentication {
    public function authenticate() {
        $user = $this->core->getQueries()->getSubmittyUser($this->user_id);
        if ($user === null) {
            return false;
        }

        $tmp_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "tmp", "pam");

        do {
            $file = md5(uniqid(rand(), true));
        } while (file_exists(FileUtils::joinPaths($tmp_path, $file)));

        $contents = json_encode(array('username' => $this->user_id, 'password' => $this->password));
        if (file_put_contents(FileUtils::joinPaths($tmp_path, $file), $contents) === false) {
            throw new AuthenticationException("Could not create tmp user PAM file.");
        }
        register_shutdown_function(function() use ($file) {
            unlink(FileUtils::joinPaths("/tmp", $file));
        });

        // Open a cURL connection so we don't have to do a weird redirect chain to authenticate
        // as that would require some hacky path handling specific to PAM authentication
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->core->getConfig()->getCgiUrl()."pam_check.cgi?file={$file}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        if ($output === false) {
            throw new AuthenticationException(curl_error($ch));
        }

        $output_after = json_decode($output, true);
        curl_close($ch);

        if ($output_after === null) {
		throw new AuthenticationException("Error JSON response for PAM: ".json_last_error_msg());
        }
        else if (!isset($output_after['authenticated'])) {
            throw new AuthenticationException("Missing response in JSON for PAM");
        }
        else if ($output_after['authenticated'] !== true) {
            return false;
        }

        return true;
    }
}
