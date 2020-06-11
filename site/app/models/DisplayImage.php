<?php

namespace app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\exceptions\FileWriteException;
use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class DisplayImage
 * @package app\models\image
 *
 * DisplayImage model represents a user's personal head shot.  Users may have multiple images in their user_data
 * directory, and DisplayImage takes care of selecting the single most appropriate image for display.
 */
class DisplayImage extends AbstractModel
{
    /** @var string[] The set of legal display_image_state which may be collected from the DB */
    const LEGAL_IMAGE_STATES = ['system', 'preferred', 'flagged'];

    /** @var string[] The set of directories for which it is legal to save a DisplayImage to */
    const LEGAL_FOLDERS = ['system_images', 'user_images'];

    /** @var int
     * When a new DisplayImage is saved, this is the maximum dimension either side may be.  Non-square images will
     * have the larger dimension resized to this constant, while the other dimension will be reduced to maintain the
     * original form factor of the image.
     */
    const IMG_MAX_DIMENSION = 500;

    /** @var string The file path to the selected image */
    protected $path;

    /** @var string The image mime type */
    protected $mime_type;

    /**
     * DisplayImage constructor
     *
     * Determines the path to the display image based on the display image state.  Also tests to ensure the image
     * is readable at instantiation time.
     *
     * @param Core $core
     * @param string $user_id
     * @param string $display_image_state
     * @throws \ImagickException There was a problem converting the image to an Imagick object
     * @throws BadArgumentException An illegal display_image_state was passed in
     * @throws FileReadException Unable to read the file at the selected location for whatever reason
     */
    public function __construct(Core $core, string $user_id, string $display_image_state) {

        parent::__construct($core);

        // Validate passed image state
        if (!in_array($display_image_state, self::LEGAL_IMAGE_STATES)) {
            throw new BadArgumentException('Unknown display_image_state!');
        }

        if ($display_image_state === 'system' || $display_image_state === 'flagged') {
            $sub_dir = 'system_images';
        } else if ($display_image_state === 'preferred') {
            $sub_dir = 'user_images';
        }

        $data_dir = $this->core->getConfig()->getSubmittyPath();
        $image_folder_dir = FileUtils::joinPaths($data_dir, 'user_data', $user_id, $sub_dir);

        // If can't open image folder then return without setting $path
        if (!is_readable($image_folder_dir)) {
            throw new FileReadException('Unable to read from the image folder.');
        }

        // Search directory for image with the highest timestamp
        $files = scandir($image_folder_dir, 1);
        $full_path = FileUtils::joinPaths($image_folder_dir, $files[0]);

        // Ensure image is readable
        if(!is_readable($full_path) || $files[0] == '.' || $files[0] == '..') {
            throw new FileReadException('Unable to read the display image.');
        }

        $imagick = new \Imagick($full_path);

        $this->mime_type = $imagick->getImageMimeType();
        $this->path = $full_path;
    }

    /**
     * Get the display image encoded as a base64 string
     *
     * You may optionally resize the image.
     *
     * You may pass both parameters to have fine control over both image dimensions.
     *
     * You may pass only a single parameter and leave the other one null.
     * Doing so will resize that dimension to the specified length, while maintaining the form factor of the image.
     *
     * @param int|null $cols Length in pixels to resize this dimension to
     * @param int|null $rows Length in pixels to resize this dimension to
     * @return string The base64 encoding of the image
     * @throws \ImagickException Unable to convert the file at $this->path to an Imagick object.
     */
    public function getImageBase64(int $cols = null, int $rows = null): string {
        $imagick = new \Imagick($this->path);

        // Handling any resizing that might need to be done
        if (!is_null($cols) || !is_null($rows)) {
            $cols_resize = $cols ?? 0;
            $rows_resize = $rows ?? 0;

            $imagick->scaleImage($cols_resize, $rows_resize);
        }

        return base64_encode($imagick->getImageBlob());
    }

    /**
     * Save a new DisplayImage image
     *
     * @param Core $core The application core
     * @param string $user_id The user_id who will own this image
     * @param string $new_image_name Name of the file to be saved, without the extension.  For example 'aphacker'
     * @param string $image_extension File extension, for example 'jpeg' or 'gif'
     * @param string $tmp_file_path Path to the temporary location of the file to work with.  This may be the temporary
     *                              path in the $_FILES array or the location of a file after unzipping a zip archive
     * @param string $folder The folder to save to, for example 'system_images' or 'user_images'
     * @throws BadArgumentException $folder is not a member of self::LEGAL_FOLDERS
     * @throws FileWriteException Unable to write to the image directory
     * @throws \ImagickException
     */
    public static function saveUserImage(Core $core, string $user_id, string $new_image_name, string $image_extension, string $tmp_file_path, string $folder): void {
        // Validate folder
        if (!in_array($folder, self::LEGAL_FOLDERS)) {
            throw new BadArgumentException('The $folder parameter must be a member of DisplayImage::LEGAL_FOLDERS.');
        }

        // Generate the path to the folder where this image should be saved
        $folder_path = FileUtils::joinPaths(
            $core->getConfig()->getSubmittyPath(),
            'user_data',
            $user_id,
            $folder
        );

        // Generate the folder if it does not exist
        if (!FileUtils::createDir($folder_path, true)) {
            throw new FileWriteException('Error creating the user\'s system images folder.');
        }

        // Decrease image size while maintaining form factor
        // If bigger image dimension is already smaller then IMG_MAX_DIMENSION don't do any resizing.
        $imagick = new \Imagick($tmp_file_path);
        $cols = $imagick->getImageWidth();
        $rows = $imagick->getImageHeight();

        if ($cols >= $rows && $cols > self::IMG_MAX_DIMENSION) {
            $imagick->scaleImage(self::IMG_MAX_DIMENSION, 0);
        }
        else if ($rows > self::IMG_MAX_DIMENSION) {
            $imagick->scaleImage(0, self::IMG_MAX_DIMENSION);
        }

        // Save file (will overwrite any image with same name)
        $imagick->writeImage(FileUtils::joinPaths($folder_path, $new_image_name . '.' . $image_extension));
    }
}
