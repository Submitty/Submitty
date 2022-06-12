<?php

namespace app\views\superuser;

use app\views\AbstractView;

class SamlManagerView extends AbstractView {
    public function renderPage(array $proxy_mapped_users): string {
        $this->core->getOutput()->addInternalModuleJs('saml-manager.js');

        $csrf_token = $this->core->getCsrfToken();

        return $this->core->getOutput()->renderTwigTemplate('superuser/SamlManager.twig', [
            'proxy_mapped_users' => $proxy_mapped_users,
            'csrf_token' => $csrf_token,
            'new_proxy_user_url' => $this->core->buildUrl(['superuser', 'saml', 'new_user']),
            'proxy_mapping_url' => $this->core->buildUrl(['superuser', 'saml', 'assign']),
            'add_all_mapping_url' => $this->core->buildUrl(['superuser', 'saml', 'add_all']),
            'change_active_saml_url' => $this->core->buildUrl(['superuser', 'saml', 'update_active']),
            'delete_mapping_url' => $this->core->buildUrl(['superuser', 'saml', 'delete'])
        ]);
    }
}
