<?php

namespace tests\app\libraries;

use \app\libraries\CascadingIterator;

class CascadingIteratorTester extends \PHPUnit\Framework\TestCase {
    public function testIterator() {

        $multi_iterator = new CascadingIterator(
            new \ArrayIterator(['file_1', 'file_2']),
            new \ArrayIterator(['file_3', 'file_4'])
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
            $this->assertEquals(($count > 1) ? 1 : 0, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(($count > 1) ? 1 : 0, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorFirst() {
        $multi_iterator = new CascadingIterator(
            new \EmptyIterator(),
            new \ArrayIterator(['file_1', 'file_2'])
        );

        $files = [
            'file_1',
            'file_2'
        ];

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(1, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(1, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorLast() {
        $multi_iterator = new CascadingIterator(
            new \ArrayIterator(['file_1', 'file_2']),
            new \EmptyIterator()
        );

        $files = [
            'file_1',
            'file_2'
        ];

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(0, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(0, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorBookend() {
        $multi_iterator = new CascadingIterator(
            new \EmptyIterator(),
            new \ArrayIterator(['file_1', 'file_2']),
            new \EmptyIterator()
        );

        $files = [
            'file_1',
            'file_2'
        ];

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(1, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(1, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorMiddle() {
        $multi_iterator = new CascadingIterator(
            new \ArrayIterator(['file_1', 'file_2']),
            new \EmptyIterator(),
            new \ArrayIterator(['file_3', 'file_4'])
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
            $this->assertEquals(($count < 2) ? 0 : 2, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(($count < 2) ? 0 : 2, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorMixStarting() {
        $multi_iterator = new CascadingIterator(
            new \EmptyIterator(),
            new \ArrayIterator(['file_1', 'file_2']),
            new \EmptyIterator(),
            new \ArrayIterator(['file_3', 'file_4'])
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
            $this->assertEquals(($count < 2) ? 1 : 3, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(($count < 2) ? 1 : 3, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }

    public function testEmptyIteratorMixEnding() {
        $multi_iterator = new CascadingIterator(
            new \ArrayIterator(['file_1', 'file_2']),
            new \EmptyIterator(),
            new \ArrayIterator(['file_3', 'file_4']),
            new \EmptyIterator()
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
            $this->assertEquals(($count < 2) ? 0 : 2, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());

        $count = 0;
        foreach ($multi_iterator as $item) {
            $this->assertEquals($count, $multi_iterator->key());
            $this->assertEquals(($count < 2) ? 0 : 2, $multi_iterator->iteratorKey());
            $this->assertEquals($files[$count], $item);
            $count++;
        }
        $this->assertNull($multi_iterator->current());
    }
}
