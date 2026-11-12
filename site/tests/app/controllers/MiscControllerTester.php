<?php

namespace tests\app\controllers;

use app\controllers\MiscController;
use app\entities\course\CourseMaterial;
use app\entities\course\CourseMaterialSection;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\SessionManager;
use app\libraries\Utils;
use app\libraries\response\MultiResponse;
use app\models\Config;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use tests\BaseUnitTest;
use tests\utils\NullOutput;

class MiscControllerTester extends BaseUnitTest {
    use \phpmock\phpunit\PHPMock;

    private $tmp_dir = '';

    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
        $_POST = [];
    }

    public static function userDataProvider() {
        $user_details = [
            'user_id' => 'test',
            'user_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ];
        for ($i = 4; $i > 0; $i--) {
            $user_details['user_group'] = $i;
            yield [$user_details];
        }
    }

    /**
     * @dataProvider userDataProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testReadFileSite($user_details): void {
        $this->getFunctionMock('app\controllers', 'header')
            ->expects($this->once())
            ->with('Content-type: text/css');

        $core = new Core();
        $user = new User($core, $user_details);
        $core->setUser($user);
        $core->setOutput(new NullOutput($core));
        $config = new Config($core);
        $core->setConfig($config);
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
        FileUtils::createDir(FileUtils::joinpaths($this->tmp_dir, 'site'));
        $override_path = FileUtils::joinPaths($this->tmp_dir, 'site', 'override.css');
        file_put_contents($override_path, "body {}");
        $config->setCoursePath($this->tmp_dir);
        $session_manager = $this->createMock(SessionManager::class);
        $session_manager->method('getCsrfToken')->willReturn('test');
        $core->setSessionManager($session_manager);

        $controller = new MiscController($core);
        ob_start();
        $result = $controller->readFile('site', $override_path, 'test');
        $file_contents = ob_get_contents();
        ob_end_clean();
        $this->assertTrue($result);
        $this->assertEquals('body {}', $file_contents);
    }

    /**
     * @dataProvider userDataProvider
     */
    public function testReadFileDirectoryTraversal($user_details): void {
        $core = new Core();
        $user = new User($core, $user_details);
        $core->setUser($user);
        $core->setOutput(new NullOutput($core));
        $config = new Config($core);
        $core->setConfig($config);
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, 'site'));
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, 'submissions'));
        $override_path = FileUtils::joinPaths($this->tmp_dir, 'site', 'override.css');
        file_put_contents(FileUtils::joinPaths($this->tmp_dir, 'submissions', 'test.py'), 'print(True)');
        file_put_contents($override_path, "body {}");
        $config->setCoursePath($this->tmp_dir);
        $session_manager = $this->createMock(SessionManager::class);
        $session_manager->method('getCsrfToken')->willReturn('test');
        $core->setSessionManager($session_manager);

        $controller = new MiscController($core);
        ob_start();
        $result = $controller->readFile(
            'site',
            FileUtils::joinPaths($this->tmp_dir, 'site', '..', 'submissions', 'test.py'),
            'test'
        );
        ob_end_clean();
        $this->assertFalse($result);
    }

    /**
     * Get the details of a test user in registration section 1.
     *
     * @return array<string, mixed>
     */
    private static function getUserDetails(int $group): array {
        return [
            'user_id' => 'test',
            'user_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => $group,
            'registration_section' => '1',
        ];
    }

    /**
     * Create a Core that uses the real Access checks on a temporary course directory with no course materials.
     *
     * @param array<string, mixed> $user_details
     */
    private function createCourseCore(array $user_details): Core {
        DateUtils::setTimezone(new \DateTimeZone('America/New_York'));
        $core = new Core();
        $core->setUser(new User($core, $user_details));
        $core->setOutput(new NullOutput($core));
        $config = new Config($core);
        $core->setConfig($config);
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
        $config->setCoursePath($this->tmp_dir);
        $session_manager = $this->createMock(SessionManager::class);
        $session_manager->method('getCsrfToken')->willReturn('test');
        $core->setSessionManager($session_manager);

        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getGradeableConfig')->willReturn($this->createMock(Gradeable::class));
        $queries->method('getGradedGradeable')->willReturn($this->createMock(GradedGradeable::class));
        $core->setQueries($queries);

        $this->useCourseMaterial($core, null);

        return $core;
    }

    /**
     * Back the course entity manager with a repository that can only find the given course material.
     */
    private function useCourseMaterial(Core $core, ?CourseMaterial $course_material): void {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($course_material) {
                if ($course_material === null) {
                    return null;
                }
                $found = isset($criteria['id'])
                    ? (int) $criteria['id'] === $course_material->getId()
                    : $criteria['path'] === $course_material->getPath();
                return $found ? $course_material : null;
            }
        );
        $entity_manager = $this->createMock(EntityManager::class);
        $entity_manager->method('getRepository')->with(CourseMaterial::class)->willReturn($repository);
        $core->setCourseEntityManager($entity_manager);
    }

    /**
     * Write a file inside the temporary course directory.
     *
     * @return string Absolute path of the file
     */
    private function writeCourseFile(string $relative_path, string $contents): string {
        $path = FileUtils::joinPaths($this->tmp_dir, $relative_path);
        FileUtils::createDir(dirname($path), true);
        file_put_contents($path, $contents);
        return $path;
    }

    /**
     * Create a course material with ID 5 that the course repository can find. Only addAccess is
     * stubbed, since the constructor leaves $accesses unset and it only works on loaded entities.
     *
     * @param string|null $section The only section allowed to view it, or null to allow every section
     * @return CourseMaterial&\PHPUnit\Framework\MockObject\MockObject
     */
    private function addCourseMaterial(Core $core, string $path, \DateTime $release_date, ?string $section = null): CourseMaterial {
        $course_material = $this->getMockBuilder(CourseMaterial::class)
            ->setConstructorArgs([CourseMaterial::FILE, $path, $release_date, false, 0.0, null, null, null, null, null, null])
            ->onlyMethods(['addAccess'])
            ->getMock();
        $course_material->setId(5);
        if ($section !== null) {
            $course_material->addSection(new CourseMaterialSection($section, $course_material));
        }
        $this->useCourseMaterial($core, $course_material);
        return $course_material;
    }

    /**
     * Expect the request to be refused with exactly this error message.
     */
    private function expectDenial(Core $core, string $message): void {
        $output = $this->getMockBuilder(NullOutput::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['showError'])
            ->getMock();
        $output->expects($this->once())->method('showError')->with($message);
        $core->setOutput($output);
    }

    /**
     * Assert that read_file refuses the path and sends none of its contents.
     */
    private function assertReadFileDenied(Core $core, string $dir, string $path): void {
        $this->expectDenial($core, MiscController::GENERIC_NO_ACCESS_MSG);

        ob_start();
        $result = (new MiscController($core))->readFile($dir, $path, 'test');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertSame('', $output);
    }

    /**
     * Request a course material from display_file for a gradeable, finding it by 'id' or by 'path'.
     *
     * @return mixed The result of displayFile
     */
    private function displayCourseMaterial(Core $core, CourseMaterial $course_material, string $lookup) {
        $controller = new MiscController($core);
        return $lookup === 'id'
            ? $controller->displayFile(null, null, 'hw1', 'test', null, (string) $course_material->getId())
            : $controller->displayFile('course_materials', $course_material->getPath(), 'hw1', 'test');
    }

    /**
     * @dataProvider userDataProvider
     */
    public function testReadFileOnlyServesSiteDirectory($user_details): void {
        $core = $this->createCourseCore($user_details);
        $path = $this->writeCourseFile('uploads/course_materials/exam.txt', 'answers');

        $this->assertReadFileDenied($core, 'course_materials', $path);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testReadFileServesTheCheckedPath(): void {
        $this->getFunctionMock('app\controllers', 'header')->expects($this->once());
        $core = $this->createCourseCore(self::getUserDetails(User::GROUP_INSTRUCTOR));
        $outside_file = $this->writeCourseFile('outside/secret.txt', 'secret');
        $this->writeCourseFile('site' . $outside_file, 'mirror');

        ob_start();
        $result = (new MiscController($core))->readFile('site', $outside_file, 'test');
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertSame('mirror', $output);
    }

    public static function directoryPathProvider() {
        yield 'empty path' => [''];
        yield 'current directory' => ['.'];
        yield 'root of the directory' => ['/'];
        yield 'subdirectory' => ['sub'];
        yield 'subdirectory with trailing slash' => ['sub/'];
    }

    /**
     * @dataProvider directoryPathProvider
     */
    public function testReadFileDeniesDirectories(string $path): void {
        $core = $this->createCourseCore(self::getUserDetails(User::GROUP_INSTRUCTOR));
        $this->writeCourseFile('site/sub/override.css', 'body {}');

        $this->assertReadFileDenied($core, 'site', $path);
    }

    public function testDownloadDeniesUnknownCourseMaterialId(): void {
        $core = $this->createCourseCore(self::getUserDetails(User::GROUP_INSTRUCTOR));
        $outside_file = $this->writeCourseFile('outside/secret.txt', 'secret');
        $this->writeCourseFile('site' . $outside_file, 'mirror');
        $this->expectDenial($core, MiscController::GENERIC_NO_ACCESS_MSG);

        ob_start();
        $result = (new MiscController($core))->downloadCourseFile('site', $outside_file, '999');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertSame('', $output);
    }

    public static function restrictedCourseMaterialProvider() {
        foreach (['id', 'path'] as $lookup) {
            yield "not released, found by $lookup" => [$lookup, new \DateTime('+1 day'), null, 'You may not access this file until it is released.'];
            yield "wrong section, found by $lookup" => [$lookup, new \DateTime('-1 day'), '2', 'Your section may not access this file.'];
        }
    }

    /**
     * @dataProvider restrictedCourseMaterialProvider
     */
    public function testDisplayFileChecksCourseMaterialWhenGivenGradeable(string $lookup, \DateTime $release_date, ?string $section, string $message): void {
        $core = $this->createCourseCore(self::getUserDetails(User::GROUP_STUDENT));
        $path = $this->writeCourseFile('uploads/course_materials/exam.txt', 'answers');
        $course_material = $this->addCourseMaterial($core, $path, $release_date, $section);
        $course_material->expects($this->never())->method('addAccess');
        $this->expectDenial($core, $message);

        $this->assertFalse($this->displayCourseMaterial($core, $course_material, $lookup));
    }

    public function testDisplayFileDeniesCourseMaterialWithoutRecordWhenGivenGradeable(): void {
        $core = $this->createCourseCore(self::getUserDetails(User::GROUP_STUDENT));
        $path = $this->writeCourseFile('uploads/course_materials/exam.txt', 'answers');
        $this->expectDenial($core, 'Course material not found');

        $this->assertFalse((new MiscController($core))->displayFile('course_materials', $path, 'hw1', 'test'));
    }

    public static function permittedCourseMaterialProvider() {
        foreach (['id', 'path'] as $lookup) {
            yield "released to every section, found by $lookup" => [$lookup, User::GROUP_STUDENT, new \DateTime('-1 day'), null];
            yield "released to the student's section, found by $lookup" => [$lookup, User::GROUP_STUDENT, new \DateTime('-1 day'), '1'];
            yield "unreleased for an instructor, found by $lookup" => [$lookup, User::GROUP_INSTRUCTOR, new \DateTime('+1 day'), null];
        }
    }

    /**
     * @dataProvider permittedCourseMaterialProvider
     */
    public function testDisplayFileAllowsPermittedCourseMaterialWhenGivenGradeable(string $lookup, int $group, \DateTime $release_date, ?string $section): void {
        $core = $this->createCourseCore(self::getUserDetails($group));
        $path = $this->writeCourseFile('uploads/course_materials/notes.txt', 'notes');
        $course_material = $this->addCourseMaterial($core, $path, $release_date, $section);
        $course_material->expects($this->once())->method('addAccess');

        $this->assertNotFalse($this->displayCourseMaterial($core, $course_material, $lookup));
    }

    /**
     * Request a file from encode_pdf as part of doej's hw1 submission. Access allows every path,
     * so only the controller's own path check can refuse it.
     */
    private function encodeSubmissionPdf(string $file_path): MultiResponse {
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getAutoGradedGradeable')->willReturn($this->createMockModel(AutoGradedGradeable::class));
        $core = $this->createMockCore(
            ['course_path' => $this->tmp_dir],
            [],
            [
                'getGradeableConfig' => $this->createMockModel(Gradeable::class),
                'getSubmitterById' => $this->createMockModel(Submitter::class),
                'getGradedGradeableForSubmitter' => $graded_gradeable,
                'getSubmitterIdFromAnonId' => null,
            ],
            ['path.read']
        );

        $_POST['user_id'] = 'doej';
        $_POST['filename'] = basename($file_path);
        $_POST['file_path'] = $file_path;
        return (new MiscController($core))->encodePDF('hw1');
    }

    public function testEncodePdfServesSubmittersFile(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $path = $this->writeCourseFile('submissions/hw1/doej/1/hw1.pdf', 'own');

        $response = $this->encodeSubmissionPdf($path);

        $this->assertSame(['status' => 'success', 'data' => base64_encode('own')], $response->json_response->json);
    }

    public function testEncodePdfDeniesSubmitterWhoseIdStartsTheSame(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->writeCourseFile('submissions/hw1/doej/1/hw1.pdf', 'own');
        $path = $this->writeCourseFile('submissions/hw1/doej2/1/hw1.pdf', 'other');

        $response = $this->encodeSubmissionPdf($path);

        $this->assertSame(['status' => 'fail', 'message' => 'Invalid file path'], $response->json_response->json);
    }
}
