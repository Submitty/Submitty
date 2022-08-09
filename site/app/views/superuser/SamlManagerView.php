<?php

namespace app\views\superuser;

use app\views\AbstractView;

class SamlManagerView extends AbstractView {
    public function renderPage(array $proxy_mapped_users, array $invalid_users = null): string {
        $this->core->getOutput()->addInternalModuleJs('saml-manager.js');

        $csrf_token = $this->core->getCsrfToken();

        $params = [
            'proxy_mapped_users' => $proxy_mapped_users,
            'csrf_token' => $csrf_token,
            'new_proxy_user_url' => $this->core->buildUrl(['superuser', 'saml', 'new_user']),
            'proxy_mapping_url' => $this->core->buildUrl(['superuser', 'saml', 'assign']),
            'validate_url' => $this->core->buildUrl(['superuser', 'saml', 'validate']),
            'change_active_saml_url' => $this->core->buildUrl(['superuser', 'saml', 'update_active']),
            'delete_mapping_url' => $this->core->buildUrl(['superuser', 'saml', 'delete'])
        ];

        if ($invalid_users !== null) {
            $params['invalid_users'] = $invalid_users;
        }

        return $this->core->getOutput()->renderTwigTemplate('superuser/SamlManager.twig', $params);
    }
}
