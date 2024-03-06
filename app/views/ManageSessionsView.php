<?php

namespace app\views;

class ManageSessionsView extends AbstractView {
    /**
     * @param array<mixed> $user_sessions
     * @return void
     */
    public function showSessionsPage(array $user_sessions) {
        $this->core->getOutput()->addBreadcrumb("Manage Sessions");
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('manage-sessions.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->renderTwigOutput("ManageSessions.twig", [
            'current_session_id' => $this->core->getCurrentSessionId(),
            'user_sessions' => $user_sessions,
            'csrf_token' => $this->core->getCsrfToken(),
            'secure_session' => $this->core->getUser()->getEnforceSingleSession(),
        ]);
    }
}
