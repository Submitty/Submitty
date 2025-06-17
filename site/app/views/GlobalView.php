<?php

namespace app\views;

use app\libraries\FileUtils;
use app\entities\banner\BannerImage;

class GlobalView extends AbstractView {
    /**
     * @param array<array<string>> $audio
     * @param array<BannerImage> $eventBannerImages
     */
    public function header($breadcrumbs, $wrapper_urls, $sidebar_buttons, $notifications_info, array $audio, $css, $js, $duck_img, $page_name, $content_only, array $eventBannerImages, bool $performance_warning) {
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

        $page_title = "Submitty";
        if ($this->core->getUser() === null) {
            $page_title = "Login";
        }
        elseif ($this->core->getConfig()->isCourseLoaded()) {
            $course_name = ucwords(strtolower($this->core->getFullCourseName()));
            // We assume that if there is no page breadcrumb (only course), we are on gradeables
            if ($course_name === ucwords(strtolower($page_name))) {
                $page_name = "Gradeables";
            }
            $page_title = $page_name . " - " . $course_name;
        }
        elseif (!empty($page_name) && $page_name !== "Submitty") {
            // $page_name !== "Submitty" is needed so we dont end up with pages with the title "Submitty Submitty"
            $page_title = $page_name;
        }

        $images_data_array = [];
        $error_image_data = '_NONE_';


        $currentDate = new \DateTime();
        foreach ($eventBannerImages as $banner) {
            $semiPath = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events", $banner->getClosingDate()->format('Y'), $banner->getFolderName());
            $pathName = FileUtils::joinPaths($semiPath, $banner->getName());
            $extraPathName = FileUtils::joinPaths($semiPath, $banner->getExtraInfo());
            if (!is_file($extraPathName)) {
                $images_data_array[] = [
                    "name" => $banner->getName(),
                    "id" => $banner->getId(),
                    "data" => base64_encode(file_get_contents($pathName)),
                    "extra_info" => "",
                    "link_name" => $banner->getLinkName()
                ];
                continue;
            }

            $extraFile = base64_encode(file_get_contents($extraPathName));

            $images_data_array[] = [
                "name" => $banner->getName(),
                "id" => $banner->getId(),
                "data" => base64_encode(file_get_contents($pathName)),
                "extra_info" => $extraFile,
                "link_name" => $banner->getLinkName()
            ];
        }

        $html_lang = str_replace('_', '-', $this->core->getConfig()->getLocale()->getName());

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "audio" => $audio,
            "css" => $css,
            "js" => $js,
            "page_title" => $page_title,
            "sidebar_buttons" => $sidebar_buttons,
            "breadcrumbs" => $breadcrumbs,
            "user_given_name" => $this->core->getUser() ? $this->core->getUser()->getDisplayedGivenName() : "",
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
            "service_worker_path" => $this->core->getOutput()->getServiceWorkerPath(),
            "imageDataArray" => $images_data_array,
            "errorImageData" => $error_image_data,
            "html_lang" => $html_lang,
            "server_time" => time(),
            "performance_warning" => $performance_warning,
            "submitty_queries" => $this->core->getSubmittyQueries(),
            "course_queries" => $this->core->getCourseQueries(),
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
