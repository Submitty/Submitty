<?php

namespace app\libraries;

use app\libraries\Utils;
use app\libraries\FileUtils;

/**
 * Class ForumUtils
 *
 * Contains various useful functions for interacting with the forum
 */
class ForumUtils {
    public const FORUM_CHAR_POST_LIMIT = 5000;

    public static function checkGoodAttachment($isThread, $thread_id, $file_post) {
        if ((!isset($_FILES[$file_post])) || $_FILES[$file_post]['error'][0] === UPLOAD_ERR_NO_FILE) {
            return [0];
        }
        if (count($_FILES[$file_post]['tmp_name']) > 5) {
            //return $this->returnUserContentToPage("Max file upload size is 5. Please try again.", $isThread, $thread_id);
        }
        $imageCheck = Utils::checkUploadedImageFile($file_post) ? 1 : 0;
        if ($imageCheck == 0 && !empty($_FILES[$file_post]['tmp_name'])) {
            //return $this->returnUserContentToPage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)", $isThread, $thread_id);
        }
        return [$imageCheck];
    }

    public static function isValidCategories($rows, $inputCategoriesIds = -1, $inputCategoriesName = -1) {
        if (is_array($inputCategoriesIds)) {
            if (count($inputCategoriesIds) < 1) {
                return false;
            }
            foreach ($inputCategoriesIds as $category_id) {
                $match_found = false;
                foreach ($rows as $index => $values) {
                    if ($values["category_id"] === $category_id) {
                        $match_found = true;
                        break;
                    }
                }
                if (!$match_found) {
                    return false;
                }
            }
        }
        if (is_array($inputCategoriesName)) {
            if (count($inputCategoriesName) < 1) {
                return false;
            }
            foreach ($inputCategoriesName as $category_name) {
                $match_found = false;
                foreach ($rows as $index => $values) {
                    if ($values["category_desc"] === $category_name) {
                        $match_found = true;
                        break;
                    }
                }
                if (!$match_found) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function getDisplayName($anonymous, $real_name) {
        if ($anonymous) {
            return "Anonymous";
        }
        return $real_name['given_name'] . substr($real_name['family_name'], 0, 2) . '.';
    }

    /**
     * @param string[] $attachment_names
     * @return array{
     *     'exist': bool,
     *     'files': mixed[],
     *     'params': mixed[]
     * }
     */
    public static function getForumAttachments(int $post_id, int $thread_id, array $attachment_names, string $course_path, string $course_url): array {
        $thread_dir = FileUtils::joinPaths($course_path, "forum_attachments", $thread_id);
        $post_attachment = ["exist" => false];

        if (count($attachment_names) > 0) {
            $post_attachment["exist"] = true;

            $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
            $files = FileUtils::getAllFiles($post_dir);

            $post_attachment["files"] = [];
            $attachment_id = "attachments_{$post_id}";
            $attachment_button_id = "button_attachments_{$post_id}";
            $attachment_file_count = 0;
            $attachment_encoded_data = [];

            foreach ($files as $file) {
                if (in_array($file['name'], $attachment_names, true)) {
                    $path = rawurlencode($file['path']);
                    $name = rawurlencode($file['name']);
                    $url = $course_url . '?dir=forum_attachments&path=' . $path;

                    $post_attachment["files"][] = [
                        "file_viewer_id" => "file_viewer_" . $post_id . "_" . $attachment_file_count
                    ];

                    $attachment_encoded_data[] = [$url, $post_id . '_' . $attachment_file_count, $name];

                    $attachment_file_count++;
                    $GLOBALS['totalAttachments']++;
                }
            }

            $attachment_encoded_data[] = $attachment_id;

            $post_attachment["params"] = [
                "well_id"   => $attachment_id,
                "button_id" => $attachment_button_id,
                "num_files" => $attachment_file_count,
                "encoded_data" => json_encode($attachment_encoded_data),
                "unencoded_data" => $attachment_encoded_data,
            ];
        }
        return $post_attachment;
    }
}
