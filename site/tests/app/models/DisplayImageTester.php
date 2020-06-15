<?php

namespace tests\app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\models\Config;
use app\models\DisplayImage;
use tests\BaseUnitTest;

class DisplayImageTester extends BaseUnitTest {

    const TEST_USER_NAME = 'abc_test_user';
    const TEST_IMAGE = 'test_image.jpeg';

    private $core;
    private $test_image_path;
    private $user_data_dir;

    public function setUp(): void {
        $this->core = $this->mockCore();

        $this->test_image_path = FileUtils::joinPaths(__TEST_DATA__, 'images', self::TEST_IMAGE);

        // Generate test folders
        $this->user_data_dir = FileUtils::joinPaths('/', 'var', 'local', 'submitty', 'user_data', self::TEST_USER_NAME, 'system_images');
        FileUtils::createDir($this->user_data_dir, true);

        // Copy a test image in
        copy($this->test_image_path, FileUtils::joinPaths($this->user_data_dir, self::TEST_IMAGE));
    }

    public function tearDown(): void {
        if (file_exists($this->user_data_dir)) {
            FileUtils::recursiveRmdir($this->user_data_dir);
        }
    }

    private function mockCore() {
        $core = $this->createMockModel(Core::class);

        $config = $this->createMockModel(Config::class);
        $config->method('getSubmittyPath')->willReturn('/var/local/submitty');
        $core->method('getConfig')->willReturn($config);

        return $core;
    }

    /**
     * Constructor exception tests
     */
    public function testConstructorBadArgument() {
        $this->expectException(BadArgumentException::class);
        $this->expectExceptionMessage('Unknown display_image_state!');
        new DisplayImage($this->core, self::TEST_USER_NAME, 'bad_image_state');
    }

    public function testConstructorNoImageFolder() {
        // Remove image folder that was created by setUp()
        FileUtils::recursiveRmdir($this->user_data_dir);

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Unable to read from the image folder.');
        new DisplayImage($this->core, self::TEST_USER_NAME, 'system');
    }

    public function testConstructorNoImageFile() {
        // Remove image that was created by setUp()
        $path = FileUtils::joinPaths($this->user_data_dir, self::TEST_IMAGE);
        unlink($path);

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Unable to read the display image.');
        new DisplayImage($this->core, self::TEST_USER_NAME, 'system');
    }

    /**
     * getImageBase64() tests
     */
    public function testGetImageBase64NoResize() {
        $imagick = new \Imagick($this->test_image_path);
        $test_image_base64 = base64_encode($imagick->getImageBlob());

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $this->assertEquals($display_image->getImageBase64(), $test_image_base64);
    }

    public function testGetImageBase64ColResize() {
        $new_col = 200;

        $test_imagick = new \Imagick($this->test_image_path);
        $test_imagick->scaleImage($new_col, 0);

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $real_imagick = new \Imagick();
        $real_imagick->readImageBlob(base64_decode($display_image->getImageBase64($new_col, null)));

        $this->assertEquals($test_imagick->getImageWidth(), $real_imagick->getImageWidth());
        $this->assertEquals($test_imagick->getImageHeight(), $real_imagick->getImageHeight());
    }

    public function testGetImageBase64RowResize() {
        $new_row = 200;

        $test_imagick = new \Imagick($this->test_image_path);
        $test_imagick->scaleImage(0, $new_row);

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $real_imagick = new \Imagick();
        $real_imagick->readImageBlob(base64_decode($display_image->getImageBase64(null, $new_row)));

        $this->assertEquals($test_imagick->getImageWidth(), $real_imagick->getImageWidth());
        $this->assertEquals($test_imagick->getImageHeight(), $real_imagick->getImageHeight());
    }

    public function testGetImageBase64BothResize() {
        $new_col = 200;
        $new_row = 200;

        $test_imagick = new \Imagick($this->test_image_path);
        $test_imagick->scaleImage($new_col, $new_row);

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $real_imagick = new \Imagick();
        $real_imagick->readImageBlob(base64_decode($display_image->getImageBase64($new_col, $new_row)));

        $this->assertEquals($test_imagick->getImageWidth(), $real_imagick->getImageWidth());
        $this->assertEquals($test_imagick->getImageHeight(), $real_imagick->getImageHeight());
    }

    /**
     * getImageBase64MaxDimension() tests
     */

    /**
     * resizeMaxDimension() tests
     */

    /**
     * saveUserImage() tests
     */
}