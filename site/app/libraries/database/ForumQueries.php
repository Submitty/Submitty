<?php

namespace app\libraries\database;

use app\models\forum\Thread;
use app\models\forum\Post;

class ForumQueries {
 //extends DatabaseQueries {


    public function createPost(Post &$p, bool $isFirst){

        $parent_id = $p->getParentId();

        if(!$isFirst && $parent_id == 0){
            $this->course_db->query("SELECT MIN(id) as id FROM posts where thread_id = ?", [ $p->getThreadId() ] );
            $parent_id = $this->course_db->rows()[0]["id"];
        }

        try {
            $this->course_db->query("INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, endorsed_by, type, has_attachment) VALUES (?, ?, ?, ?, current_timestamp, ?, ?, ?, ?, ?)", array($p->getThreadId(), $p->getParentId(), $p->getAuthor(), $p->getContent(), $anonymous, 0, null, $type, $hasAttachment));
            $this->course_db->query("SELECT MAX(id) as max_id from posts where thread_id=? and author_user_id=?", [ $p->getThreadId(), $p->getUser() ] );
        } catch (DatabaseException $dbException){
            if($this->course_db->inTransaction()){
                $this->course_db->rollback();
                return [false, -1, -1];
            }
        }

        return [true, $this->course_db->rows()[0]["max_id"]];
    }


    public function createThread(Thread &$t) {

        $this->course_db->beginTransaction();

        try {
            $this->course_db->query("INSERT INTO threads (title, created_by, pinned, status, deleted, merged_thread_id, merged_post_id, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", array($t->getTitle(), $t->getUser(), $t->isPinned(), $t->getStatus(), 0, -1, -1, true));
        } catch(DatabaseException $dbException) {
            $this->course_db->rollback();
            return false;
        }

        //retrieve generated thread_id
        $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", array($t->getTitle(), $t->getUser()));
        //Max id will be the most recent post
        $id = $this->course_db->rows()[0]["max_id"];

        foreach ($categories_ids as $category_id) {
            try {
                $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES (?, ?)", array($id, $category_id));
            } catch(DatabaseException $dbException) {
                $this->course_db->rollback();
                return false;
            }
        }

        $t->getFirst()->setThreadId($id);

        $post_result = $this->createPost($t->getFirst());

        if(!$post_result) {
            $t->getFirst()->setThreadId(-1);
            return $post_result;
        }

        $this->course_db->commit();

        //Will change...
        return [true, 'thread_id' => $id, 'post_id' => $post_id];
    }


}
