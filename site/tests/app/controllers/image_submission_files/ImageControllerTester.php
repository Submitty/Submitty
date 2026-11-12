<?php

namespace tests\app\controllers\image_submission_files;

use app\controllers\image_submission_files\ImageController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use tests\BaseUnitTest;

class ImageControllerTester extends BaseUnitTest {
    private $tmp_path;
    private $course_path;
    private $core;

    public function setUp(): void {
        $this->tmp_path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        // getSubmittedFilePath() takes the 10th path segment to be the submitter id, as in
        // /var/local/submitty/courses/<term>/<course>/<dir>/<gradeable>/<submitter>,
        // so pad the course path out to the same depth
        $padding = 3 - substr_count($this->tmp_path, '/');
        if ($padding < 0) {
            $this->markTestSkipped('The temporary directory is nested too deeply');
        }
        $this->course_path = FileUtils::joinPaths($this->tmp_path, str_repeat('submitty/', $padding) . 'courses/f24/sample');

        $files = [
            'submissions/hw1/alice/1/image.png',
            'submissions_processed/hw1/alice/1/image.png',
            'checkout/hw1/alice/1/image.png',
            'results/hw1/alice/1/image.png',
            'results_public/hw1/alice/1/image.png',
            'uploads/hw1/alice/1/image.png',
            'submissions/hw2/alice/1/image.png',
            'secret.png'
        ];
        foreach ($files as $file) {
            $file = FileUtils::joinPaths($this->course_path, $file);
            FileUtils::createDir(dirname($file), true);
            file_put_contents($file, 'contents');
        }
        symlink(
            FileUtils::joinPaths($this->course_path, 'secret.png'),
            FileUtils::joinPaths($this->course_path, 'submissions/hw1/alice/1/link.png')
        );

        $auto_graded_gradeable = $this->createMockModel(AutoGradedGradeable::class);
        $auto_graded_gradeable->method('getActiveVersion')->willReturn(1);
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getAutoGradedGradeable')->willReturn($auto_graded_gradeable);

        $this->core = $this->createMockCore(
            ['course_path' => $this->course_path],
            [],
            [
                'getSubmitterIdFromAnonId' => 'alice',
                'getGradeableConfig' => $this->createMockModel(Gradeable::class),
                'getGradedGradeable' => $graded_gradeable
            ],
            ['grading.electronic.grade']
        );
    }

    public function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_path);
        $_POST = [];
    }

    /**
     * Returns the rendered output, which is empty when the image is shown (rendering is disabled in tests)
     * and the JSON failure response otherwise.
     */
    private function showGraderImageEmbedded(string $path): string {
        $_POST['user_id'] = 'anon_alice';
        $_POST['filename'] = basename($path);
        $_POST['file_path'] = FileUtils::joinPaths($this->course_path, $path);
        $output = $this->core->getOutput();
        $output->useHeader(false);
        $output->useFooter(false);
        $controller = new ImageController($this->core);
        $controller->showGraderImageEmbedded('hw1');
        return $output->getOutput();
    }

    public static function allowedPathProvider(): array {
        return [
            'submissions' => ['submissions/hw1/anon_alice/1/image.png'],
            'submissions_processed' => ['submissions_processed/hw1/anon_alice/1/image.png'],
            'checkout' => ['checkout/hw1/anon_alice/1/image.png'],
            'results' => ['results/hw1/anon_alice/1/image.png'],
            'results_public' => ['results_public/hw1/anon_alice/1/image.png']
        ];
    }

    /**
     * @dataProvider allowedPathProvider
     */
    public function testAllowedPath(string $path) {
        $this->assertSame('', $this->showGraderImageEmbedded($path));
    }

    public static function deniedPathProvider(): array {
        return [
            'other gradeable' => ['submissions/hw2/anon_alice/1/image.png'],
            'other course directory' => ['uploads/hw1/anon_alice/1/image.png'],
            'traversal out of submitter' => ['submissions/hw1/anon_alice/1/../../../../secret.png'],
            'symlink out of submitter' => ['submissions/hw1/anon_alice/1/link.png'],
            'missing file' => ['submissions/hw1/anon_alice/1/missing.png']
        ];
    }

    /**
     * @dataProvider deniedPathProvider
     */
    public function testDeniedPath(string $path) {
        $this->assertEquals(
            ['status' => 'fail', 'message' => 'The image file could not be found'],
            json_decode($this->showGraderImageEmbedded($path), true)
        );
    }
}
