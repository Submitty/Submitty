<?php

namespace tests\app\libraries;

use app\libraries\FileUtils;
use \app\libraries\CascadingIterator;
use app\libraries\Utils;

class CascadingIteratorTester extends \PHPUnit\Framework\TestCase {
    public function testIterator() {
        $temp_dir1 = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $temp_dir2 = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());

        try {
            FileUtils::createDir($temp_dir1);
            touch(FileUtils::joinPaths($temp_dir1, 'file_1'));
            touch(FileUtils::joinPaths($temp_dir1, 'file_2'));

            FileUtils::createDir($temp_dir2);
            touch(FileUtils::joinPaths($temp_dir2, 'file_3'));
            touch(FileUtils::joinPaths($temp_dir2, 'file_4'));

            $multi_iterator = new CascadingIterator(
                new \FilesystemIterator($temp_dir1, \RecursiveDirectoryIterator::SKIP_DOTS),
                new \FilesystemIterator($temp_dir2, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $files = [
                'file_1',
                'file_2',
                'file_3',
                'file_4'
            ];

            $iterator_files = [];
            $count = 0;
            foreach ($multi_iterator as $item) {
                $this->assertEquals($count, $multi_iterator->key());
                $this->assertEquals(($count > 1) ? 1 : 0, $multi_iterator->iteratorKey());
                $iterator_files[] = $item->getFilename();
                $count++;
            }
            sort($iterator_files);
            $this->assertEquals($files, $iterator_files);
            $this->assertNull($multi_iterator->current());

            $iterator_files = [];
            $count = 0;
            foreach ($multi_iterator as $item) {
                $this->assertEquals($count, $multi_iterator->key());
                $this->assertEquals(($count > 1) ? 1 : 0, $multi_iterator->iteratorKey());
                $iterator_files[] = $item->getFilename();
                $count++;
            }
            sort($iterator_files);
            $this->assertEquals($files, $iterator_files);
            $this->assertNull($multi_iterator->current());
        }
        finally {
            if (file_exists($temp_dir1)) {
                FileUtils::recursiveRmdir($temp_dir1);
            }
            if (file_exists($temp_dir2)) {
                FileUtils::recursiveRmdir($temp_dir2);
            }
        }
    }
}
