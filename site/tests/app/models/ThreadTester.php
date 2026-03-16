<?php

use PHPUnit\Framework\TestCase;
use app\entities\forum\Thread;
use app\entities\forum\Post;
use app\entities\forum\ThreadAccess;
use app\entities\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;

class ThreadTester extends TestCase {
    private $thread;
    private $author;

    protected function setUp(): void {
        $this->author = $this->createMock(UserEntity::class);
        $this->author->method('getId')->willReturn('author');
        $this->thread = $this->getMockBuilder(Thread::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthor'])
            ->getMock();
        $this->thread->method('getAuthor')->willReturn($this->author);
        $this->setProtectedProperty($this->thread, 'id', 42);
        $this->setProtectedProperty($this->thread, 'title', 'Test Thread');
        $this->setProtectedProperty($this->thread, 'author', $this->author);
        $this->setProtectedProperty($this->thread, 'deleted', false);
        $this->setProtectedProperty($this->thread, 'pinned_expiration', (new DateTime('+2 days')));
        $this->setProtectedProperty($this->thread, 'lock_thread_date', null);
        $this->setProtectedProperty($this->thread, 'status', 1);
        $this->setProtectedProperty($this->thread, 'merged_thread', null);
        $this->setProtectedProperty($this->thread, 'announced', null);
        $this->setProtectedProperty($this->thread, 'posts', new ArrayCollection());
        $this->setProtectedProperty($this->thread, 'categories', new ArrayCollection());
        $this->setProtectedProperty($this->thread, 'viewers', new ArrayCollection());
        $this->setProtectedProperty($this->thread, 'favorers', new ArrayCollection());
    }

    private function setProtectedProperty($object, $property, $value) {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    public function testGettersAndSetters() {
        $this->assertEquals(42, $this->thread->getId());
        $this->assertEquals('Test Thread', $this->thread->getTitle());
        $this->thread->setTitle('New Title');
        $this->assertEquals('New Title', $this->thread->getTitle());
        $this->assertSame($this->author, $this->thread->getAuthor());
        $this->assertFalse($this->thread->isDeleted());
        $this->thread->setDeleted(true);
        $this->assertTrue($this->thread->isDeleted());
        $this->assertEquals(1, $this->thread->getStatus());
        $this->thread->setStatus(2);
        $this->assertEquals(2, $this->thread->getStatus());
    }

    public function testPinnedExpirationAndLock() {
        $future = new DateTime('+2 days');
        $past = new DateTime('-2 days');
        $this->setProtectedProperty($this->thread, 'pinned_expiration', $future);
        $this->assertTrue($this->thread->isPinned());
        $this->setProtectedProperty($this->thread, 'pinned_expiration', $past);
        $this->assertFalse($this->thread->isPinned());
        $this->setProtectedProperty($this->thread, 'lock_thread_date', $past);
        $this->assertTrue($this->thread->isLocked());
        $this->setProtectedProperty($this->thread, 'lock_thread_date', $future);
        $this->assertFalse($this->thread->isLocked());
    }

    public function testMergedThread() {
        $this->setProtectedProperty($this->thread, 'merged_thread', null);
        $this->assertFalse($this->thread->isMergedThread());
        $merged = $this->getMockBuilder(Thread::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $merged->method('getId')->willReturn(99);
        $this->setProtectedProperty($this->thread, 'merged_thread', $merged);
        $this->assertTrue($this->thread->isMergedThread());
    }

    public function testAnnounced() {
        $this->setProtectedProperty($this->thread, 'announced', null);
        $this->assertFalse($this->thread->isAnnounced());
        $this->setProtectedProperty($this->thread, 'announced', new DateTime());
        $this->assertTrue($this->thread->isAnnounced());
    }

    public function testCategories() {
        $cats = new ArrayCollection(['cat1', 'cat2']);
        $this->thread->setCategories($cats);
        $this->assertSame($cats, $this->thread->getCategories());
    }

    public function testIsSearchMatch() {
        $this->setProtectedProperty($this->thread, 'title', 'FindMe');
        $this->assertTrue($this->thread->isSearchMatch('findme'));
        $this->assertFalse($this->thread->isSearchMatch('notfound'));
    }

    public function testIsFavorite() {
        $favorer = $this->getMockBuilder(stdClass::class)
            ->addMethods(['getUserId'])
            ->getMock();
        $favorer->method('getUserId')->willReturn('favuser');
        $this->setProtectedProperty($this->thread, 'favorers', new ArrayCollection([$favorer]));
        $this->assertTrue($this->thread->isFavorite('favuser'));
        $this->assertFalse($this->thread->isFavorite('otheruser'));
    }

    public function testGetFirstPost() {
        // Create a mock parent post with getId() === -1
        $mockParent = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $mockParent->method('getId')->willReturn(-1);
        // Create a post whose getParent() returns the mock parent
        $rootPost = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParent', 'getId'])
            ->getMock();
        $rootPost->method('getParent')->willReturn($mockParent);
        $rootPost->method('getId')->willReturn(1);
        $posts = new ArrayCollection([$rootPost]);
        $this->setProtectedProperty($this->thread, 'posts', $posts);
        $this->assertSame($rootPost, $this->thread->getFirstPost());
    }

    public function testGetSumUpducks() {
        $post1 = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUpduckers'])
            ->getMock();
        $post1->method('getUpduckers')->willReturn(new ArrayCollection([1,2]));
        $post2 = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUpduckers'])
            ->getMock();
        $post2->method('getUpduckers')->willReturn(new ArrayCollection([3]));
        $posts = new ArrayCollection([$post1, $post2]);
        $this->setProtectedProperty($this->thread, 'posts', $posts);
        $this->assertEquals(3, $this->thread->getSumUpducks());
    }
}

