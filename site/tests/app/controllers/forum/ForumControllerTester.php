<?php

namespace tests\app\controllers\forum;

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
}
