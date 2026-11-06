<?php

namespace tests\app\controllers\image_submission_files;

use app\controllers\image_submission_files\PDFController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use tests\BaseUnitTest;

class PDFControllerTester extends BaseUnitTest {
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
            'submissions/hw1/alice/1/file.pdf',
            'submissions_processed/hw1/alice/1/file.pdf',
            'checkout/hw1/alice/1/file.pdf',
            'results/hw1/alice/1/file.pdf',
            'results_public/hw1/alice/1/file.pdf',
            'uploads/hw1/alice/1/file.pdf',
            'submissions/hw1/bob/1/file.pdf',
            'submissions/hw1/alice2/1/file.pdf',
            'submissions/hw2/alice/1/file.pdf',
            'secret.pdf'
        ];
        foreach ($files as $file) {
            $file = FileUtils::joinPaths($this->course_path, $file);
            FileUtils::createDir(dirname($file), true);
            file_put_contents($file, 'contents');
        }
        symlink(
            FileUtils::joinPaths($this->course_path, 'secret.pdf'),
            FileUtils::joinPaths($this->course_path, 'submissions/hw1/alice/1/link.pdf')
        );

        $this->core = $this->createMockCore(
            ['course_path' => $this->course_path],
            [],
            [
                'getSubmitterIdFromAnonId' => 'alice',
                'getGradeableConfig' => $this->createMockModel(Gradeable::class),
                'getGradedGradeable' => $this->createMockModel(GradedGradeable::class)
            ],
            ['grading.electronic.grade']
        );
    }

    public function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_path);
        $_POST = [];
    }

    private function showGraderPDFEmbedded(string $user_id, string $path, bool $is_anon): ?JsonResponse {
        $_POST['user_id'] = $user_id;
        $_POST['filename'] = basename($path);
        $_POST['file_path'] = FileUtils::joinPaths($this->course_path, $path);
        $_POST['is_anon'] = $is_anon ? 'true' : 'false';
        $controller = new PDFController($this->core);
        return $controller->showGraderPDFEmbedded('hw1');
    }

    public static function allowedPathProvider(): array {
        return [
            'submissions' => ['submissions/hw1/alice/1/file.pdf'],
            'submissions_processed' => ['submissions_processed/hw1/alice/1/file.pdf'],
            'checkout' => ['checkout/hw1/alice/1/file.pdf'],
            'results' => ['results/hw1/alice/1/file.pdf'],
            'results_public' => ['results_public/hw1/alice/1/file.pdf']
        ];
    }

    /**
     * @dataProvider allowedPathProvider
     */
    public function testAllowedPath(string $path) {
        $this->assertNull($this->showGraderPDFEmbedded('alice', $path, false));
    }

    public static function deniedPathProvider(): array {
        return [
            'other submitter' => ['submissions/hw1/bob/1/file.pdf'],
            'submitter sharing a prefix' => ['submissions/hw1/alice2/1/file.pdf'],
            'other gradeable' => ['submissions/hw2/alice/1/file.pdf'],
            'other course directory' => ['uploads/hw1/alice/1/file.pdf'],
            'traversal out of submitter' => ['submissions/hw1/alice/1/../../../../secret.pdf'],
            'symlink out of submitter' => ['submissions/hw1/alice/1/link.pdf'],
            'missing file' => ['submissions/hw1/alice/1/missing.pdf']
        ];
    }

    /**
     * @dataProvider deniedPathProvider
     */
    public function testDeniedPath(string $path) {
        $response = $this->showGraderPDFEmbedded('alice', $path, false);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(['status' => 'error', 'message' => 'The PDF file could not be found'], $response->json);
    }

    public function testAllowedAnonymousPath() {
        $this->assertNull($this->showGraderPDFEmbedded('anon_alice', 'submissions/hw1/anon_alice/1/file.pdf', true));
    }

    public function testDeniedAnonymousPath() {
        $response = $this->showGraderPDFEmbedded('anon_alice', 'uploads/hw1/anon_alice/1/file.pdf', true);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(['status' => 'error', 'message' => 'The PDF file could not be found'], $response->json);
    }
}
