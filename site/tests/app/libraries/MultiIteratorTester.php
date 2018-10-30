<?php

namespace tests\app\libraries;

use app\libraries\FileUtils;
use \app\libraries\MultiIterator;
use app\libraries\Utils;

class MultiIteratorTester extends \PHPUnit\Framework\TestCase {
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

            $multi_iterator = new MultiIterator(
                new \FilesystemIterator($temp_dir1, \RecursiveDirectoryIterator::SKIP_DOTS),
                new \FilesystemIterator($temp_dir2, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $files = [
                'file_1',
                'file_2',
                'file_3',
                'file_4'
            ];

            $count = 0;
            foreach ($multi_iterator as $item) {
                $this->assertEquals($count, $multi_iterator->key());
                $this->assertEquals($files[$count], $item->getFilename());
                $count++;
            }

            $this->assertNull($multi_iterator->current());

            $count = 0;
            foreach ($multi_iterator as $item) {
                $this->assertEquals($count, $multi_iterator->key());
                $this->assertEquals($files[$count], $item->getFilename());
                $count++;
            }
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
