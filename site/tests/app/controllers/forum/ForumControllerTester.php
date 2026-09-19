<?php

namespace tests\app\controllers\forum;

use app\entities\UserEntity;
use app\entities\forum\Post;
use app\entities\forum\Thread;
use ReflectionClass;
use ReflectionMethod;
use app\controllers\forum\ForumController;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\DatabaseQueries;
use app\models\Config;
use app\models\User;
use tests\BaseUnitTest;

class ForumControllerTester extends BaseUnitTest {
    private function createControllerWithCapturedStats(array $deleted_post_counts, ?User $submitty_user): array {
        $core = new Core();

        $config = $this->createMockModel(Config::class);
        $config->method('getTimezone')->willReturn(new \DateTimeZone("America/New_York"));
        $core->setConfig($config);

        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getPosts')->willReturn([]);
        $queries->method('getUpDucks')->willReturn([]);
        $queries->method('getSubmittyUser')->willReturn($submitty_user);
        $core->setQueries($queries);

        $course_db = new class ($deleted_post_counts) extends AbstractDatabase {
            private array $deleted_post_counts;
            public string $last_query = '';

            public function __construct(array $deleted_post_counts) {
                parent::__construct([]);
                $this->deleted_post_counts = $deleted_post_counts;
            }

            public function getConnectionDetails(): array {
                return [];
            }

            public function fromDatabaseToPHPArray($text, $parse_bools = false, $start = 0, &$end = null): array {
                return [];
            }

            public function fromPHPToDatabaseArray($array): string {
                return '{}';
            }

            public function query($query, $parameters = []): void {
                $this->last_query = $query;
            }

            public function rows(): array {
                return $this->deleted_post_counts;
            }
        };
        $core->setCourseDatabase($course_db);

        $output = new class ($core) extends Output {
            public ?array $rendered_output = null;

            public function renderOutput($view, string $function, ...$args) {
                $this->rendered_output = [
                    'view' => $view,
                    'function' => $function,
                    'args' => $args,
                ];
                return null;
            }
        };
        $core->setOutput($output);

        return [new ForumController($core), $output, $course_db];
    }

    public function testShowStatsIncludesUserWithOnlyDeletedPosts(): void {
        $user = $this->createMockModel(User::class);
        $user->method('getDisplayedGivenName')->willReturn('Deleted');
        $user->method('getDisplayedFamilyName')->willReturn('User');

        [$controller, $output, $course_db] = $this->createControllerWithCapturedStats(
            [
                ['author_user_id' => 'deleted_only', 'num_deleted_posts' => '2'],
            ],
            $user
        );

        $controller->showStats();

        $this->assertNotNull($output->rendered_output);
        $this->assertSame('forum\\ForumThread', $output->rendered_output['view']);
        $this->assertSame('statPage', $output->rendered_output['function']);
        $this->assertStringContainsString('GROUP BY author_user_id', $course_db->last_query);

        $users = $output->rendered_output['args'][0];
        $this->assertCount(1, $users);
        $this->assertArrayHasKey('deleted_only', $users);
        $this->assertSame('Deleted', $users['deleted_only']['given_name']);
        $this->assertSame('User', $users['deleted_only']['family_name']);
        $this->assertSame(2, $users['deleted_only']['num_deleted_posts']);
        $this->assertSame(0, $users['deleted_only']['total_threads']);
        $this->assertSame(0, $users['deleted_only']['total_upducks']);
        $this->assertSame([], $users['deleted_only']['posts']);
        $this->assertSame([], $users['deleted_only']['id']);
        $this->assertSame([], $users['deleted_only']['timestamps']);
        $this->assertSame([], $users['deleted_only']['thread_id']);
        $this->assertSame([], $users['deleted_only']['thread_title']);
    }

    /**
     * Builds a ForumController whose notification body helper can be exercised in isolation, without
     * needing a database or a real HTTP request.
     *
     * The current user is a regular student who is neither the post author nor staff, so
     * modifyAnonymous() is false and the post's anonymous flag alone decides whether the author's
     * name may be shown.
     */
    private function createControllerForNotificationBody(): ForumController {
        $core = new Core();
        $core->setConfig($this->createMockModel(Config::class));
        $user = $this->createMockModel(User::class);
        $user->method('accessFullGrading')->willReturn(false);
        $user->method('accessGrading')->willReturn(false);
        $user->method('accessAdmin')->willReturn(false);
        $user->method('getId')->willReturn('some_other_student');
        $core->setUser($user);
        return new ForumController($core);
    }

    /**
     * Creates a Post entity with no persistence: it only needs author/editor/thread wired up so the
     * notification body helper can read them.
     */
    private function createPost(UserEntity $author, Thread $thread, bool $anonymous, string $content = 'Hello world'): Post {
        $post = new Post($thread);
        $reflected = new ReflectionClass(Post::class);
        foreach (['id' => 1, 'author' => $author, 'content' => $content, 'anonymous' => $anonymous] as $name => $value) {
            $property = $reflected->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($post, $value);
        }
        return $post;
    }

    private function createUserEntity(string $id, string $given_name, string $family_name): UserEntity {
        $user = new UserEntity();
        $reflected = new ReflectionClass(UserEntity::class);
        $values = ['user_id' => $id, 'user_givenname' => $given_name, 'user_familyname' => $family_name];
        foreach ($values as $name => $value) {
            $property = $reflected->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($user, $value);
        }
        return $user;
    }

    private function createThread(string $title, UserEntity $author): Thread {
        $thread = new Thread();
        $thread->setTitle($title);
        $property = (new ReflectionClass(Thread::class))->getProperty('author');
        $property->setAccessible(true);
        $property->setValue($thread, $author);
        return $thread;
    }

    /**
     * Invokes the private notification body helper and returns the built subject/content pair.
     *
     * @return string[] { 'subject': string, 'content': string }
     */
    private function buildNotificationBody(Post $post, UserEntity $editor, bool $did_edit_thread): array {
        $controller = $this->createControllerForNotificationBody();
        $method = new ReflectionMethod(ForumController::class, 'editedPostNotificationBody');
        $method->setAccessible(true);
        return $method->invoke($controller, $post, $editor, $did_edit_thread);
    }

    public function testEditedPostNotificationBodyNamesAuthorAndEditorWhenDifferent(): void {
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $editor = $this->createUserEntity('instructor', 'Bob', 'Brown');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), false);

        $body = $this->buildNotificationBody($post, $editor, false);

        $this->assertStringContainsString('Original Author: Alice Anderson', $body['content']);
        $this->assertStringContainsString('Edited By: Bob Brown', $body['content']);
        $this->assertSame('Post Edited: Hello world', $body['subject']);
    }

    public function testEditedThreadNotificationBodyNamesAuthorAndEditorWhenDifferent(): void {
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $editor = $this->createUserEntity('instructor', 'Bob', 'Brown');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), false);

        $body = $this->buildNotificationBody($post, $editor, true);

        $this->assertStringContainsString('Original Author: Alice Anderson', $body['content']);
        $this->assertStringContainsString('Edited By: Bob Brown', $body['content']);
        $this->assertSame('Thread Edited: Homework 1', $body['subject']);
    }

    public function testEditedPostNotificationBodyOmitsAuthorWhenAuthorIsEditor(): void {
        // A user editing their own post must not get a notification implying someone else wrote it.
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), false);

        $body = $this->buildNotificationBody($post, $author, false);

        $this->assertStringNotContainsString('Original Author', $body['content']);
        $this->assertStringNotContainsString('Edited By', $body['content']);
        $this->assertStringContainsString('A message was edited in:', $body['content']);
    }

    public function testEditedPostNotificationBodyNeverRevealsAnonymousAuthor(): void {
        // An anonymous post's author name must never appear in the notification, even when a
        // different (non-anonymous) person edited it.
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $editor = $this->createUserEntity('instructor', 'Bob', 'Brown');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), true);

        $body = $this->buildNotificationBody($post, $editor, false);

        $this->assertStringNotContainsString('Alice Anderson', $body['content']);
        $this->assertStringContainsString('Original Author: Anonymous', $body['content']);
        $this->assertStringContainsString('Edited By: Bob Brown', $body['content']);
    }

    public function testEditedPostNotificationBodyUsesPreferredNames(): void {
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), false);
        // A preferred name must win over the legal name, matching how the rest of Submitty displays names.
        $reflected = new ReflectionClass(UserEntity::class);
        $preferred_given = $reflected->getProperty('user_preferred_givenname');
        $preferred_given->setAccessible(true);
        $preferred_given->setValue($author, 'Aly');

        $body = $this->buildNotificationBody($post, $author, false);

        $this->assertStringNotContainsString('Alice', $body['content']);
    }

    public function testEditedPostNotificationBodyTruncatesLongContentInSubject(): void {
        $long = str_repeat('a', 400);
        $author = $this->createUserEntity('student', 'Alice', 'Anderson');
        $editor = $this->createUserEntity('instructor', 'Bob', 'Brown');
        $post = $this->createPost($author, $this->createThread('Homework 1', $author), false, $long);

        $body = $this->buildNotificationBody($post, $editor, false);

        $this->assertStringContainsString('...(truncated)', $body['subject']);
        $this->assertStringContainsString('...(truncated)', $body['content']);
    }
}
