<?php

namespace app\views;
use app\models\User;
use app\libraries\FileUtils;
use DirectoryIterator;

use app\entities\banner\BannerImage;
use app\repositories\banner\BannerImageRepository;

use app\libraries\Core;
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
        $entity_manager = $this->core->getBannerEntityManager()->getRepository(BannerImage::class);
        self::addBannerImage($images_data_array, $error_image_data, $this->core->getConfig()->getSubmittyPath(), $entity_manager);

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
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
        ]);
    }

    public function footer($runtime, $wrapper_urls, $footer_links, $content_only, bool $performance_warning) {
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
            "performance_warning" => $performance_warning
        ]);
    }

public static function addBannerImage(&$images_data_array, &$error_image_data, $submitty_path, &$entity_manager) {
    $base_course_material_path = FileUtils::joinPaths($submitty_path, 'banner_images');

    if (!file_exists($base_course_material_path)) {
        return;
    }

    // Get the current year and month
    $currentYear = intval(date('Y'));

    // Create a DirectoryIterator for the base course material path
    $directoryIterator = new \DirectoryIterator($base_course_material_path);
    foreach ($directoryIterator as $fileInfo) {
        // Check if the item is a directory and matches the format "yyyy-mm"
        if ($fileInfo->isDir() && preg_match('/^\d{4}$/', $fileInfo->getBasename())) {

            // Convert to integers for comparison
            $year = $fileInfo->getBasename();

            // Check if the folder's year and month are same or later than today
            if ($year >= $currentYear) {
                // Loop through each file in the directory
                $directoryPath = $fileInfo->getPathname();
                $monthDirectoryIterator = new \DirectoryIterator($directoryPath);
                foreach ($monthDirectoryIterator as $monthFileInfo) {
                    // Exclude directories and dot files
                    if ($monthFileInfo->isFile() && !$monthFileInfo->isDot()) {
                        if (!$monthFileInfo->valid()) {
                            continue;
                        }

                        $expected_image = $monthFileInfo->getPathname();
                        $content_type = FileUtils::getContentType($expected_image);
                        if (substr($content_type, 0, 5) === "image") {
                            // Read image path, convert to base64 encoding
                            $expected_img_data = base64_encode(file_get_contents($expected_image));
                            $img_name = $monthFileInfo->getBasename('.png');
                            $banner_item = $entity_manager->findBy(['name' => $img_name . ".png"])[0];
                            $extra_info_name = $banner_item->getExtraInfo();
                            $extra_info_name = "man.png";
                            $lastSlashPos = strrpos($expected_image, '/');
                            if ($lastSlashPos !== false) {
                                $extra_file_path = substr_replace($expected_image, $extra_info_name, $lastSlashPos + 1);
                            }
                            echo $extra_file_path;
                            $extra_img_data = base64_encode(file_get_contents($extra_file_path));


                            if ($img_name === "error_image") {
                                $error_image_data = $expected_img_data;
                            } else {
                                $date_now = new \DateTime();
                                if ($banner_item->getReleaseDate() <= $date_now && $date_now <= $banner_item->getClosingDate()) {
                                    $images_data_array[] = [
                                        "name" => $img_name,
                                        "data" => $expected_img_data,
                                        "extra_info" => $extra_img_data
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
}
