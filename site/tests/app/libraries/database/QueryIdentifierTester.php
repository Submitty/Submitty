<?php

namespace tests\app\libraries\database;

use app\libraries\database\QueryIdentifier;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

class QueryIdentifierTester extends \PHPUnit\Framework\TestCase {
    public function dataProvider() {
      return [
        ['SELECT * FROM foo', QueryIdentifier::SELECT],
        ['DELETE FROM thread_categories where thread_id=?', QueryIdentifier::DELETE],
        ['UPDATE threads set merged_thread_id=-1, merged_post_id=-1 where id=?', QueryIdentifier::UPDATE],
        ['INSERT INTO thread_categories (thread_id, category_id) VALUES (?,?)', QueryIdentifier::INSERT],
        ['CREATE TABLE foo (id int)', QueryIdentifier::UNKNOWN],
      ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testIdentify($query, $expected) {
      $this->assertEquals($expected, QueryIdentifier::identify($query));
    }

    public function testCteSelectIdentify() {
      $query = <<<SQL
        WITH
        A AS
        (SELECT registration_section, user_id, user_firstname, user_lastname
        FROM users
        ORDER BY registration_section, user_lastname, user_firstname, user_id),
        B AS
        (SELECT distinct on (user_id) user_id, timestamp
        FROM gradeable_access where user_id is not NULL
        ORDER BY user_id, timestamp desc),
        C AS
        (SELECT distinct on (user_id) user_id,submission_time
        FROM electronic_gradeable_data
        ORDER BY user_id, submission_time),
        D AS
        (SELECT distinct on (user_id) user_id, timestamp
        FROM viewed_responses
        ORDER BY user_id, timestamp desc),
        E AS
        (SELECT distinct on (author_user_id) author_user_id, timestamp
        FROM posts
        ORDER BY author_user_id, timestamp desc),
        F AS
        (SELECT student_id, count (student_id)
        FROM poll_responses
        GROUP BY student_id),
        G AS
        (SELECT distinct on (user_id) user_id, time_in
        FROM queue
        ORDER BY user_id, time_in desc)
        SELECT
        A.registration_section, A.user_id, user_firstname, user_lastname,
        B.timestamp as gradeable_access,
        C.submission_time as gradeable_submission,
        D.timestamp as forum_view,
        E.timestamp as forum_post,
        F.count as num_poll_responses,
        G.time_in as office_hours_queue
        FROM
        A
        left join B on A.user_id=B.user_id
        left join C on A.user_id=C.user_id
        left join D on A.user_id=D.user_id
        left join E on A.user_id=E.author_user_id
        left join F on A.user_id=F.student_id
        left join G on A.user_id=G.user_id
        ORDER BY A.registration_section, A.user_lastname, A.user_firstname, A.user_id;
SQL;
        $this->assertEquals(QueryIdentifier::SELECT, QueryIdentifier::identify($query));
    }
}
