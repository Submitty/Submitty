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
    const TEST_IMAGE = 'test_image.gif';

    private $core;
    private $test_image_path;
    private $user_data_dir;

    public function setUp(): void {
        $this->core = $this->mockCore();

//        $this->test_image_path = FileUtils::joinPaths(__TEST_DATA__, 'images', self::TEST_IMAGE);
        $this->test_image_path = __DIR__ . '/../../data/images/' . self::TEST_IMAGE;
        echo $this->test_image_path;

        // Generate test folders
        $this->user_data_dir = FileUtils::joinPaths('/', 'var', 'local', 'submitty', 'user_data', self::TEST_USER_NAME, 'system_images');
        echo $this->user_data_dir;

        $res = FileUtils::createDir($this->user_data_dir, true);
        echo $res;

        // Copy a test image in
        $res2 = copy($this->test_image_path, FileUtils::joinPaths($this->user_data_dir, self::TEST_IMAGE));
        echo $res2;
    }

    public function tearDown(): void {
        if (file_exists($this->user_data_dir)) {
            FileUtils::recursiveRmdir($this->user_data_dir);
        }
    }

    /**
     * Helpers
     */
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

    public function getImageBase64ResizeProvider() {
        return [
            [null, null],
            [200, null],
            [null, 200],
        ];
    }

    /**
     * @dataProvider getImageBase64ResizeProvider
     */
    public function testGetImageBase64Resize($new_cols, $new_rows) {
        $test_imagick = new \Imagick($this->test_image_path);

        if (!is_null($new_cols) || !is_null($new_rows)) {
            $test_cols = $new_cols ?? 0;
            $test_rows = $new_rows ?? 0;
            $test_imagick->scaleImage($test_cols, $test_rows);
        }

        $test_image_base64 = base64_encode($test_imagick->getImageBlob());

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $display_imagick = new \Imagick();
        $display_image_base64 = $display_image->getImageBase64($new_cols, $new_rows);
        $display_imagick->readImageBlob(base64_decode($display_image_base64));

        $this->assertEquals($test_imagick->getImageWidth(), $display_imagick->getImageWidth());
        $this->assertEquals($test_imagick->getImageHeight(), $display_imagick->getImageHeight());
        $this->assertEquals($display_image_base64, $test_image_base64);
    }

    /**
     * getImageBase64MaxDimension() tests
     */
    public function testGetImageBase64MaxDimension() {
        // Test image has cols > rows
        // Result should be 500 cols and rows < 500
        $new_col = 500;

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $imagick = new \Imagick();
        $imagick->readImageBlob(base64_decode($display_image->getImageBase64MaxDimension($new_col)));

        $this->assertEquals($imagick->getImageWidth(), $new_col);
        $this->assertTrue($imagick->getImageWidth() > $imagick->getImageHeight());
    }

    /**
     * saveUserImage() tests
     */
    public function testSaveUserImageBadArgument() {
        $this->expectException(BadArgumentException::class);
        $this->expectExceptionMessage('The $folder parameter must be a member of DisplayImage::LEGAL_FOLDERS.');
        DisplayImage::saveUserImage(
            $this->core,
            self::TEST_USER_NAME,
            'test',
            'gif',
            $this->test_image_path,
            'bad_folder'
        );
    }

    public function saveUserImageProvider() {
        $meta = explode('.', self::TEST_IMAGE);
        $file_name = $meta[0];
        $file_extension = $meta[1];

        $core = $this->mockCore();
        $test_image_path = FileUtils::joinPaths(__TEST_DATA__, 'images', self::TEST_IMAGE);

        return [
            [$core, self::TEST_USER_NAME, $file_name, $file_extension, $test_image_path, 'system_images'],
            [$core, self::TEST_USER_NAME, $file_name, $file_extension, $test_image_path, 'user_images'],
        ];
    }

    /**
     * @dataProvider saveUserImageProvider
     */
    public function testSaveUserImage($core, string $user_id, string $new_image_name, string $image_extension, string $tmp_file_path, string $folder) {
        DisplayImage::saveUserImage($core, $user_id, $new_image_name, $image_extension, $tmp_file_path, $folder);

        $path = FileUtils::joinPaths('/', 'var', 'local', 'submitty', 'user_data', $user_id, $folder, "$new_image_name.$image_extension");
        $this->assertFileExists($path);
    }
}
