<?php

namespace app\models;

use app\exceptions\BadArgumentException;
use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class DisplayImage
 * @package app\models\image
 *
 * DisplayImage model represents a user's personal head shot.  Users may have multiple images in their user_data
 * directory, and DisplayImage takes care of selecting the most single most appropriate image for display.
 */
class DisplayImage extends AbstractModel
{
    const LEGAL_IMAGE_STATES = ['system', 'preferred', 'flagged'];

    protected $path;

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
            return;
        }

        // Search directory for image with the highest timestamp
        $files = scandir($image_folder_dir, 1);
        $full_path = FileUtils::joinPaths($image_folder_dir, $files[0]);

        // Ensure image is readable
        if(!is_readable($full_path) || $files[0] == '.' || $files[0] == '..') {
            return;
        }

        $this->path = $full_path;
    }
}
