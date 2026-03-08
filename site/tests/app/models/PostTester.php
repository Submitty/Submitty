<?php

use PHPUnit\Framework\TestCase;
use app\entities\forum\Post;
use app\entities\forum\Thread;
use app\entities\forum\ThreadAccess;
use app\entities\forum\PostHistory;
use app\entities\forum\PostAttachment;
use app\entities\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;

class PostTester extends TestCase {
    private $thread;
    private $author;
    private $post;

    protected function setUp(): void {
        // Minimal UserEntity stub
        $this->author = $this->createMock(UserEntity::class);
        $this->author->method('getId')->willReturn('test_user');
        // Minimal Thread stub
        $this->thread = $this->getMockBuilder(Thread::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthor'])
            ->getMock();
        $this->thread->method('getAuthor')->willReturn($this->author);
        // Create Post
        $this->post = new Post($this->thread);
        // Set up collections
        $this->setProtectedProperty($this->post, 'children', new ArrayCollection());
        $this->setProtectedProperty($this->post, 'history', new ArrayCollection());
        $this->setProtectedProperty($this->post, 'attachments', new ArrayCollection());
        $this->setProtectedProperty($this->post, 'upduckers', new ArrayCollection());
    }

    private function setProtectedProperty($object, $property, $value) {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    public function testConstructorDefaults() {
        $this->assertEquals('', $this->post->getContent());
        $this->assertTrue($this->post->isAnonymous());
        $this->assertFalse($this->post->isDeleted());
        $this->assertFalse($this->post->isRenderMarkdown());
        $this->assertEquals(-1, $this->post->getId());
        $this->assertSame($this->author, $this->post->getAuthor());
        $this->assertSame($this->thread, $this->post->getThread());
        $this->assertInstanceOf(ArrayCollection::class, $this->post->getChildren());
        $this->assertInstanceOf(ArrayCollection::class, $this->post->getHistory());
        $this->assertInstanceOf(ArrayCollection::class, $this->post->getAttachments());
        $this->assertInstanceOf(ArrayCollection::class, $this->post->getUpduckers());
        $this->assertEquals(1, $this->post->getReplyLevel());
    }

    public function testSettersAndGetters() {
        $this->post->setContent('Hello');
        $this->assertEquals('Hello', $this->post->getContent());
        $this->post->setAnonymous(false);
        $this->assertFalse($this->post->isAnonymous());
        $this->post->setDeleted(true);
        $this->assertTrue($this->post->isDeleted());
        $this->post->setRenderMarkdown(true);
        $this->assertTrue($this->post->isRenderMarkdown());
        $this->post->setReplyLevel(3);
        $this->assertEquals(3, $this->post->getReplyLevel());
    }

    public function testIsSearchMatch() {
        $this->post->setContent('The quick brown fox');
        $this->assertTrue($this->post->isSearchMatch('quick'));
        $this->assertTrue($this->post->isSearchMatch('QUICK'));
        $this->assertFalse($this->post->isSearchMatch('dog'));
    }

    public function testSaveNewVersion() {
        $edit_author = $this->createMock(UserEntity::class);
        $history = $this->post->getHistory();
        $this->assertCount(0, $history);
        $version1 = $this->post->saveNewVersion($edit_author);
        $this->assertInstanceOf(PostHistory::class, $version1);
        $this->assertCount(1, $this->post->getHistory());
        $version2 = $this->post->saveNewVersion($edit_author);
        $this->assertEquals($version1->getVersion() + 1, $version2->getVersion());
        $this->assertCount(2, $this->post->getHistory());
    }

    public function testAddAndDeleteAttachment() {
        $attachment = $this->post->addAttachment('file.txt', 1);
        $this->assertInstanceOf(PostAttachment::class, $attachment);
        $this->assertEquals('file.txt', $attachment->getFileName());
        $this->assertCount(1, $this->post->getAttachments());
        // Mark as deleted
        $this->post->deleteAttachment('file.txt', 2);
        $this->assertEquals(2, $attachment->getVersionDeleted());
    }

    private function makeViewMock(DateTime $viewTimestamp): ThreadAccess {
        $view = $this->getMockBuilder(ThreadAccess::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTimestamp'])
            ->getMock();
        $view->method('getTimestamp')->willReturn($viewTimestamp);
        return $view;
    }

    // isUnread (no history): view timestamp is before post timestamp → unread
    public function testIsUnreadNoHistoryTrue() {
        $now = new DateTime();
        $this->setProtectedProperty($this->post, 'timestamp', $now);
        $view = $this->makeViewMock((clone $now)->modify('-1 day'));
        $this->assertTrue($this->post->isUnread($view));
    }

    // isUnread (no history): view timestamp is after post timestamp → not unread
    public function testIsUnreadNoHistoryFalse() {
        $now = new DateTime();
        $this->setProtectedProperty($this->post, 'timestamp', $now);
        $view = $this->makeViewMock((clone $now)->modify('+1 day'));
        $this->assertFalse($this->post->isUnread($view));
    }

    // isUnread (with history): view timestamp is before latest edit → unread
    public function testIsUnreadWithHistoryTrue() {
        $now = new DateTime();
        $this->setProtectedProperty($this->post, 'timestamp', $now);
        $edit_author = $this->createMock(UserEntity::class);
        $history = $this->post->saveNewVersion($edit_author);
        $editTime = (clone $now)->modify('+2 day');
        $ref = new ReflectionClass($history);
        $prop = $ref->getProperty('edit_timestamp');
        $prop->setAccessible(true);
        $prop->setValue($history, $editTime);
        // View is before the edit → unread
        $view = $this->makeViewMock((clone $now)->modify('-1 day'));
        $this->assertTrue($this->post->isUnread($view));
    }

    // isUnread (with history): view timestamp is after latest edit → not unread
    public function testIsUnreadWithHistoryFalse() {
        $now = new DateTime();
        $this->setProtectedProperty($this->post, 'timestamp', $now);
        $edit_author = $this->createMock(UserEntity::class);
        $history = $this->post->saveNewVersion($edit_author);
        $editTime = (clone $now)->modify('+2 day');
        $ref = new ReflectionClass($history);
        $prop = $ref->getProperty('edit_timestamp');
        $prop->setAccessible(true);
        $prop->setValue($history, $editTime);
        // View is after the edit → not unread
        $view = $this->makeViewMock((clone $editTime)->modify('+1 day'));
        $this->assertFalse($this->post->isUnread($view));
    }
}

