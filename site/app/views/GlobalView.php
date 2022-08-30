<?php

namespace app\views;

class GlobalView extends AbstractView {
    public function header($breadcrumbs, $wrapper_urls, $sidebar_buttons, $notifications_info, $css, $js, $duck_img, $page_name, $content_only) {
        $messages = [];
        foreach (['error', 'notice', 'success'] as $type) {
            foreach ($_SESSION['messages'][$type] as $key => $error) {
                $messages[] = [
                    "type" => $type,
                    "key" => $key,
                    "error" => $error
                ];

                unset($_SESSION['messages'][$type][$key]);
            }
        }

        $course_name = ucwords(strtolower($this->core->getFullCourseName()));
        // We assume that if there is no page breadcrumb (only course), we are on gradeables
        if ($course_name == ucwords(strtolower($page_name))) {
            $page_name = "Gradeables";
        }

        $page_title = "Submitty";
        if ($this->core->getUser() === null) {
            $page_title = "Login";
        }
        elseif ($this->core->getConfig()->isCourseLoaded()) {
            $page_title = $page_name . " - " . $course_name;
        }
        elseif (!empty($page_name) && $page_name !== "Submitty") {
            // $page_name !== "Submitty" is needed so we dont end up with pages with the title "Submitty Submitty"
            $page_title = $page_name;
        }

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "css" => $css,
            "js" => $js,
            "page_title" => $page_title,
            "sidebar_buttons" => $sidebar_buttons,
            "breadcrumbs" => $breadcrumbs,
            "user_first_name" => $this->core->getUser() ? $this->core->getUser()->getDisplayedFirstName() : "",
            "base_url" => $this->core->getConfig()->getBaseUrl(),
            "course_url" => $this->core->buildCourseUrl(),
            "websocket_port" => $this->core->getConfig()->getWebsocketPort(),
            "notifications_info" => $notifications_info,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "wrapper_urls" => $wrapper_urls,
            "system_message" => $this->core->getConfig()->getSystemMessage(),
            "csrf_token" => $this->core->getCsrfToken(),
            "enable_banner" => $this->core->getConfig()->isDuckBannerEnabled(),
            "duck_img" => $duck_img,
            "use_mobile_viewport" => $this->output->useMobileViewport(),
            "sysadmin_email" => $this->core->getConfig()->getSysAdminEmail(),
            "collapse_sidebar" => array_key_exists('collapse_sidebar', $_COOKIE) && $_COOKIE['collapse_sidebar'] === 'true',
            "content_only" => $content_only,
            "manifast_path" => $this->core->getOutput()->getManifastPath(),
            "service_worker_path" => $this->core->getOutput()->getServiceWorkerPath()
        ]);
    }

    public function footer($runtime, $wrapper_urls, $footer_links, $content_only) {
        return $this->core->getOutput()->renderTwigTemplate("GlobalFooter.twig", [
            "runtime" => $runtime,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "is_debug" => $this->core->getConfig()->isDebug(),
            "submitty_queries" => $this->core->getSubmittyQueries(),
            "course_queries" => $this->core->getCourseQueries(),
            "wrapper_urls" => $wrapper_urls,
            "latest_tag" => $this->core->getConfig()->getLatestTag(),
            "latest_commit" => $this->core->getConfig()->getLatestCommit(),
            "footer_links" => $footer_links,
            "module_js" => $this->output->getModuleJs(),
            "content_only" => $content_only,
        ]);
    }
}
