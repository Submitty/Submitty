<?php

namespace tests\app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\DisplayImage;
use tests\BaseUnitTest;

class DisplayImageTester extends BaseUnitTest {
    const TEST_USER_NAME = 'abc_test_user';
    const TEST_IMAGE = 'test_image.gif';

    private $core;
    private $test_image_path;
    private $tmp_dir;
    private $user_data_dir;

    public function setUp(): void {
        // Travis can't write to /var/local/submitty so instead use a temp dir for testing
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());

        $this->core = $this->getMockCore($this->tmp_dir);

        $this->test_image_path = FileUtils::joinPaths(__TEST_DATA__, 'images', self::TEST_IMAGE);

        // Generate test folders
        $this->user_data_dir = FileUtils::joinPaths($this->tmp_dir, 'user_data', self::TEST_USER_NAME, 'system_images');

        FileUtils::createDir($this->user_data_dir, true);

        // Copy a test image in
        copy($this->test_image_path, FileUtils::joinPaths($this->user_data_dir, self::TEST_IMAGE));
    }

    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
    }

    /**
     * Helpers
     */
    private function getMockCore(string $tmp_dir) {
        return $this->createMockCore([
            'tmp_path' => $tmp_dir
        ]);
    }

    /**
     * Constructor exception tests
     */
    public function testConstructorBadArgument(): void {
        $this->expectException(BadArgumentException::class);
        $this->expectExceptionMessage('Unknown display_image_state!');
        new DisplayImage($this->core, self::TEST_USER_NAME, 'bad_image_state');
    }

    public function testConstructorNoImageFolder(): void {
        // Remove image folder that was created by setUp()
        FileUtils::recursiveRmdir($this->user_data_dir);

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Unable to read from the image folder.');
        new DisplayImage($this->core, self::TEST_USER_NAME, 'system');
    }

    public function testConstructorNoImageFile(): void {
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
    public function testGetImageBase64NoResize(): void {
        $imagick = new \Imagick($this->test_image_path);
        $test_image_base64 = base64_encode($imagick->getImageBlob());

        $display_image = new DisplayImage($this->core, self::TEST_USER_NAME, 'system');

        $this->assertEquals($display_image->getImageBase64(), $test_image_base64);
    }

    public static function getImageBase64ResizeProvider(): array {
        return [
            [null, null],
            [200, null],
            [null, 200],
        ];
    }

    /**
     * @dataProvider getImageBase64ResizeProvider
     */
    public function testGetImageBase64Resize($new_cols, $new_rows): void {
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
    public function testGetImageBase64MaxDimension(): void {
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
    public function testSaveUserImageBadArgument(): void {
        $this->expectException(BadArgumentException::class);
        $this->expectExceptionMessage('The $folder parameter must be a member of DisplayImage::LEGAL_FOLDERS.');
        DisplayImage::saveUserImage(
            $this->core,
            self::TEST_USER_NAME,
            'gif',
            $this->test_image_path,
            'bad_folder'
        );
    }

    public function saveUserImageProvider(): array {
        $meta = explode('.', self::TEST_IMAGE);
        $file_extension = $meta[1];
        $test_image_path = FileUtils::joinPaths(__TEST_DATA__, 'images', self::TEST_IMAGE);

        return [
            [self::TEST_USER_NAME, $file_extension, $test_image_path, 'system_images'],
            [self::TEST_USER_NAME, $file_extension, $test_image_path, 'user_images'],
        ];
    }

    /**
     * @dataProvider saveUserImageProvider
     */
    public function testSaveUserImage(string $user_id, string $image_extension, string $tmp_file_path, string $folder): void {
        $core = $this->getMockCore(FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString()));
        DisplayImage::saveUserImage($core, $user_id, $image_extension, $tmp_file_path, $folder);

        $path = FileUtils::joinPaths($core->getConfig()->getSubmittyPath(), 'user_data', $user_id, $folder);

        // Ensure a file was saved, can't know precisely what the name will be since it is based on a time stamp
        $this->assertCount(1, FileUtils::getAllFilesTrimSearchPath($path, 0));
    }
}
