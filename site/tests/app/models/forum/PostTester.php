<?php

declare(strict_types=1);

namespace tests\app\models\forum;

use app\libraries\Core;
use app\models\Config;
use app\models\forum\Post;
use app\models\User;

class PostTester extends \PHPUnit\Framework\TestCase {
    /**
     * @var Core
     */
    private $core;

    public function setup(): void {
        $this->core = new Core();
        $config = new Config($this->core);
        $config->setSemester('s20');
        $config->setCourse('sample');
        $user = new User($this->core, [
            'user_id' => 'test_user',
            'user_firstname' => 'Tester',
            'user_lastname' => 'Test',
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'time_zone' => 'America/New_York'
        ]);
        $this->core->setUser($user);
        $this->core->setConfig($config);
    }

    public function testEmptyPost(): void {
        // Create a Post object with no post-details
        $post = new Post($this->core);
        // all of its properties such as thread_id, parent_id, content, and anon should be null
        $this->assertNull($post->getThreadId());
        $this->assertNull($post->getParentId());
        $this->assertNull($post->getContent());
        $this->assertNull($post->getIsAnonymous());
    }

    public function testNormalPost(): void {
        $postDetails = [
            'thread_id' => 1,
            'parent_id' => 2,
            'content' => "This is the content for testing",
            'anon' => true
        ];
        $post = new Post($this->core, $postDetails);
        // Check if the postDetails are set correctly
        $this->assertEquals($postDetails['thread_id'], $post->getThreadId());
        $this->assertEquals($postDetails['parent_id'], $post->getParentId());
        $this->assertEquals($postDetails['content'], $post->getContent());
        $this->assertEquals($postDetails['anon'], $post->getIsAnonymous());

        $newPostDetails = [
            'id' => 3,
            'thread_id' => 11,
            'parent_id' => 22,
            'author' => $this->core->getUser(),
            'content' => "This is the new content for testing",
            'anon' => false,
        ];
        // Update the properties of $post with the help of various setter
        $post->setId($newPostDetails['id']);
        $post->setThreadId($newPostDetails['thread_id']);
        $post->setParentId($newPostDetails['parent_id']);
        $post->setAuthor($newPostDetails['author']);
        $post->setContent($newPostDetails['content']);
        $post->setIsAnonymous($newPostDetails['anon']);
        // Test if the properties are updated correctly or not
        $this->assertEquals($newPostDetails['id'], $post->getId());
        $this->assertEquals($newPostDetails['thread_id'], $post->getThreadId());
        $this->assertEquals($newPostDetails['parent_id'], $post->getParentId());
        $this->assertEquals($newPostDetails['author'], $post->getAuthor());
        $this->assertEquals($newPostDetails['content'], $post->getContent());
        $this->assertEquals($newPostDetails['anon'], $post->getIsAnonymous());
    }
}
