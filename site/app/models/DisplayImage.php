<?php

namespace app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\exceptions\FileWriteException;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;

/**
 * Class DisplayImage
 * @package app\models\image
 *
 * DisplayImage model represents a user's personal head shot.  Users may have multiple images in their user_data
 * directory, and DisplayImage takes care of selecting the single most appropriate image for display.
 *
 * @method string getPath()
 * @method string setPath()
 * @method string getMimeType()
 * @method string setMimeType()
 */
class DisplayImage extends AbstractModel {
    /** string[] The set of legal display_image_state which may be collected from the DB */
    const LEGAL_IMAGE_STATES = ['system', 'preferred', 'flagged'];

    /** string[] The set of directories for which it is legal to save a DisplayImage to */
    const LEGAL_FOLDERS = ['system_images', 'user_images'];

    /**
     * int
     * When a new DisplayImage is saved, this is the maximum dimension either side may be.  Non-square images will
     * have the larger dimension resized to this constant, while the other dimension will be reduced to maintain the
     * original form factor of the image.
     */
    const IMG_MAX_DIMENSION = 500;

    /** @prop @var string The file path to the selected image */
    protected $path;

    /** @prop @var string The image mime type */
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
     * @throws BadArgumentException An illegal display_image_state was passed in
     * @throws FileReadException Unable to read the file at the selected location for whatever reason
     * @throws \ImagickException
     */
    public function __construct(Core $core, string $user_id, string $display_image_state) {
        parent::__construct($core);

        // Validate passed image state
        if (!in_array($display_image_state, self::LEGAL_IMAGE_STATES)) {
            throw new BadArgumentException('Unknown display_image_state!');
        }

        $sub_dir = $display_image_state === 'preferred' ? 'user_images' : 'system_images';

        $data_dir = $this->core->getConfig()->getSubmittyPath();
        $image_folder_dir = FileUtils::joinPaths($data_dir, 'user_data', $user_id, $sub_dir);

        // If can't open image folder then return without setting $path
        if (!is_readable($image_folder_dir)) {
            throw new FileReadException('Unable to read from the image folder.');
        }

        // Order files by newest time stamp first
        $files = FileUtils::getAllFilesTrimSearchPath($image_folder_dir, 0);
        rsort($files);

        // Ensure image is readable
        if (empty($files) || !is_readable($files[0])) {
            throw new FileReadException('Unable to read the display image.');
        }

        $imagick = new \Imagick($files[0]);

        $this->mime_type = $imagick->getImageMimeType();
        $this->path = $files[0];
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
     * @throws \ImagickException
     */
    public function getImageBase64(?int $cols = null, ?int $rows = null): string {
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
     * Get the display image encoded as a base64 string.  Resize the image so that the greater dimension is equal
     * to $max_dimension.  This resize maintains form factor of the image, so the other dimension will always be
     * smaller than $max_dimension, unless the image was square, then it will be equal to $max_dimension.
     *
     * @param int $max_dimension Length in pixels the greater image dimension should be resized to
     * @return string The base64 encoding of the image
     * @throws \ImagickException
     */
    public function getImageBase64MaxDimension(int $max_dimension) {
        $imagick = new \Imagick($this->path);

        // Handle resizing
        self::resizeMaxDimension($imagick, $max_dimension);

        return base64_encode($imagick->getImageBlob());
    }

    /**
     * Resize an imagick object so that the larger of it's two dimensions is now equal to $max_dimension.  Maintain
     * form factor.  If the larger dimension is already smaller than $max_dimension then don't do anything.
     *
     * @param \Imagick $image The image to resize
     * @param int $max_dimension Length in pixels to resize the larger dimension to
     * @throws \ImagickException
     */
    public static function resizeMaxDimension(\Imagick &$image, int $max_dimension): void {
        $cols = $image->getImageWidth();
        $rows = $image->getImageHeight();

        if ($cols >= $rows && $cols > $max_dimension) {
            $image->scaleImage($max_dimension, 0);
        }
        elseif ($rows > $max_dimension) {
            $image->scaleImage(0, $max_dimension);
        }
    }

    /**
     * Save a new DisplayImage image into the file directory
     *
     * @param Core $core The application core
     * @param string $user_id The user_id who will own this image
     * @param string $image_extension File extension, for example 'jpeg' or 'gif'
     * @param string $tmp_file_path Path to the temporary location of the file to work with.  This may be the temporary
     *                              path in the $_FILES array or the location of a file after unzipping a zip archive
     * @param string $folder The folder to save to, for example 'system_images' or 'user_images'
     * @throws BadArgumentException $folder is not a member of self::LEGAL_FOLDERS
     * @throws FileWriteException Unable to write to the image directory
     * @throws \ImagickException
     */
    public static function saveUserImage(Core $core, string $user_id, string $image_extension, string $tmp_file_path, string $folder): void {
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
        self::resizeMaxDimension($imagick, self::IMG_MAX_DIMENSION);

        // Save file where the image name is the current time stamp
        $imagick->writeImage(FileUtils::joinPaths($folder_path, DateUtils::getFileNameTimeStamp() . '.' . $image_extension));
    }
}
