<?php

namespace app\libraries;

use app\libraries\Utils;
use app\libraries\FileUtils;
use app\models\forum\Thread;
use app\models\forum\Post;

/**
 * Class ForumUtils
 *
 * Contains various useful functions for interacting with the forum
 */
class ForumUtils {

    const FORUM_CHAR_POST_LIMIT = 5000;

    public static function checkGoodAttachment($isThread, $thread_id, $file_post){
        if((!isset($_FILES[$file_post])) || $_FILES[$file_post]['error'][0] === UPLOAD_ERR_NO_FILE){
            return array(0);
        }
        if(count($_FILES[$file_post]['tmp_name']) > 5) {
            //return $this->returnUserContentToPage("Max file upload size is 5. Please try again.", $isThread, $thread_id);
        }
        $imageCheck = Utils::checkUploadedImageFile($file_post) ? 1 : 0;
        if($imageCheck == 0 && !empty($_FILES[$file_post]['tmp_name'])){
            //return $this->returnUserContentToPage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)", $isThread, $thread_id);
        }
        return array($imageCheck);
    }

    public static function isValidCategories($rows, $inputCategoriesIds = -1, $inputCategoriesName = -1){
        if(is_array($inputCategoriesIds)) {
            if(count($inputCategoriesIds) < 1) {
                return false;
            }
            foreach ($inputCategoriesIds as $category_id) {
                $match_found = false;
                foreach($rows as $index => $values){
                    if($values["category_id"] === $category_id) {
                        $match_found = true;
                        break;
                    }
                }
                if(!$match_found) {
                    return false;
                }
            }
        }
        if(is_array($inputCategoriesName)) {
            if(count($inputCategoriesName) < 1) {
                return false;
            }
            foreach ($inputCategoriesName as $category_name) {
                $match_found = false;
                foreach($rows as $index => $values){
                    if($values["category_desc"] === $category_name) {
                        $match_found = true;
                        break;
                    }
                }
                if(!$match_found) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function getDisplayName($anonymous, $real_name) {
        if($anonymous) {
            return "Anonymous";
        }
        return $real_name['first_name'] . substr($real_name['last_name'], 0, 2) . '.';
    }
}
