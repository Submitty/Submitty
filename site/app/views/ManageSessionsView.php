<?php

namespace app\views;

class ManageSessionsView extends AbstractView {
    public function showSessionsPage() {
        $this->core->getOutput()->addBreadcrumb("Manage Sessions");
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('manage-sessions.css');
        $this->core->getOutput()->addInternalJs('manage-sessions.js');
        $this->core->getOutput()->enableMobileViewport();
        $user_id = $this->core->getUser()->getId();
        $user_sessions = $this->core->getQueries()->getSessionsInfoByUser($user_id);
        $this->core->getOutput()->renderTwigOutput("ManageSessions.twig", [
            'user_sessions' => $user_sessions,
            'current_session_id' => $this->core->getCurrentSessionId(),
            'csrf_token' => $this->core->getCsrfToken(),
            'secure_session' => $this->core->getUser()->getEnforceSecureSession()
        ]);
    }
}
