<?php

namespace tests\app\libraries;

use app\libraries\FileUtils;
use app\libraries\ImageAnnotationUtils;
use app\libraries\Utils;
use PHPUnit\Framework\TestCase;

class ImageAnnotationUtilsTester extends TestCase {
    private $course_path;
    private $annotation_dir;

    public function setUp(): void {
        $this->course_path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->annotation_dir = FileUtils::joinPaths($this->course_path, 'annotations/hw1/alice/1');
        $files = [
            'submissions/hw1/alice/1/image.png',
            'submissions/hw1/alice/1/folder/image.png',
            'submissions_processed/hw1/alice/1/image.png',
            'results/hw1/alice/1/image.png',
            'submissions/hw1/bob/1/image.png'
        ];
        foreach ($files as $file) {
            $file = FileUtils::joinPaths($this->course_path, $file);
            FileUtils::createDir(dirname($file), true);
            file_put_contents($file, 'contents');
        }
        FileUtils::createDir($this->annotation_dir, true);
    }

    public function tearDown(): void {
        FileUtils::recursiveRmdir($this->course_path);
    }

    private function getKey(string $path): ?string {
        return ImageAnnotationUtils::getAnnotationKey(FileUtils::joinPaths($this->course_path, $path), $this->course_path, 'hw1', 'alice');
    }

    private function writeAnnotation(string $file_hash_source, string $grader_id, array $markers): void {
        $annotation = ['file_path' => $file_hash_source, 'grader_id' => $grader_id, 'annotations' => ['markers' => $markers]];
        file_put_contents(FileUtils::joinPaths($this->annotation_dir, md5($file_hash_source) . '_' . $grader_id . '.json'), json_encode($annotation));
    }

    public function testAnnotationKeys() {
        $this->assertSame('submissions/image.png', $this->getKey('submissions/hw1/alice/1/image.png'));
        $this->assertSame('submissions/folder/image.png', $this->getKey('submissions/hw1/alice/1/folder/image.png'));
        $this->assertSame('submissions_processed/image.png', $this->getKey('submissions_processed/hw1/alice/1/image.png'));
    }

    public function testInvalidAnnotationKeys() {
        $this->assertNull($this->getKey('results/hw1/alice/1/image.png'));
        $this->assertNull($this->getKey('submissions/hw1/bob/1/image.png'));
        $this->assertNull($this->getKey('submissions/hw1/alice/1/missing.png'));
        $this->assertNull($this->getKey('submissions/hw1/alice/1/../../bob/1/image.png'));
        $this->assertNull($this->getKey('submissions/hw1/alice/1'));
    }

    public function testLoadAnnotations() {
        $this->writeAnnotation('submissions/image.png', 'ta', [1]);
        $this->writeAnnotation('submissions_processed/image.png', 'instructor', [2]);
        $this->assertSame(
            ['ta' => json_encode(['markers' => [1]])],
            ImageAnnotationUtils::loadAnnotationJsons($this->annotation_dir, 'submissions/image.png')
        );
    }

    public function testLoadLegacyAnnotations() {
        $legacy_path = FileUtils::joinPaths($this->course_path, 'submissions_processed/hw1/alice/1/image.png');
        $this->writeAnnotation($legacy_path, 'ta', [1]);
        $this->writeAnnotation($legacy_path, 'instructor', [2]);
        $this->writeAnnotation('submissions_processed/image.png', 'instructor', [3]);
        $this->assertEquals(
            ['ta' => json_encode(['markers' => [1]]), 'instructor' => json_encode(['markers' => [3]])],
            ImageAnnotationUtils::loadAnnotationJsons($this->annotation_dir, 'submissions_processed/image.png', [$legacy_path])
        );
    }

    public function testRemoveLegacyAnnotationFiles() {
        $legacy_path = FileUtils::joinPaths($this->course_path, 'submissions_processed/hw1/alice/1/image.png');
        $this->writeAnnotation($legacy_path, 'ta', [1]);
        $this->writeAnnotation($legacy_path, 'instructor', [2]);
        ImageAnnotationUtils::removeLegacyAnnotationFiles($this->annotation_dir, [$legacy_path], 'ta');
        $this->assertEquals(
            ['instructor' => json_encode(['markers' => [2]])],
            ImageAnnotationUtils::loadAnnotationJsons($this->annotation_dir, 'submissions_processed/image.png', [$legacy_path])
        );
    }
}
