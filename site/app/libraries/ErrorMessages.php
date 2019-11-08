<?php

namespace app\libraries;

/**
 * Class ErrorMessages
 *
 * Helper class for converting PHP error codes into actual messages that can be displayed
 * to the user so they don't have to try and look up these codes in the PHP manual
 */
class ErrorMessages {
    
    /**
     * Given error code from the $_FILES['file']['error'] array, will return a human string for the error
     *
     * @param int $code Error code from upload
     *
     * @return string Message for what went wront with upload
     */
    public static function uploadErrors($code) {
        switch ($code) {
            case UPLOAD_ERR_OK:
                return "No error.";
            case UPLOAD_ERR_INI_SIZE:
                return "Max size of file exceeds allowed maximum size specified by PHP.";
            case UPLOAD_ERR_FORM_SIZE:
                return "Max size of file exceeds allowed maximum size specified by the form.";
            case UPLOAD_ERR_PARTIAL:
                return "The file was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk.";
            case UPLOAD_ERR_EXTENSION:
                return "A PHP extension caused the upload to fail.";
            default:
                return "Unknown error code.";
        }
    }
}
