<?php

namespace app\views;

use app\entities\GitAuthToken;
use app\libraries\FileUtils;

class GitAuthView extends AbstractView {
    public function showGitAuthPage(array $tokens, ?array $token = null) {
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addInternalModuleJs('git-auth-tokens.js');
        $new_token_name = null;
        $new_token_val = null;
        if ($token !== null) {
            foreach ($tokens as $t) {
                /** @var GitAuthToken $t */
                if (array_key_exists($t->getId(), $token)) {
                    $new_token_name = $t->getName();
                    $new_token_val = $token[$t->getId()];
                }
            }
        }

        $params = [
            "tokens" => $tokens,
            "csrf_token" => $this->core->getCsrfToken(),
            "current_time" => $this->core->getDateTimeNow()
        ];
        if ($new_token_name !== null && $new_token_val !== null) {
            $params["new_token_name"] = $new_token_name;
            $params["new_token_val"] = $new_token_val;
        }

        return $this->output->renderTwigTemplate('GitAuthTokens.twig', $params);
    }
}
