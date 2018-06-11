def up(conn):
    with conn.cursor() as cursor:
        cursor.execute("""
CREATE TABLE regrade_requests (
  "id" serial NOT NULL, 
  "gradeable_id" VARCHAR(255) NOT NULL,
  "timestamp" TIMESTAMP NOT NULL,
  "student_user_id" VARCHAR(255) NOT NULL,
  "status" INTEGER DEFAULT 0 NOT NULL
)
""")

        cursor.execute("""
CREATE TABLE "regrade_discussion" (
  "id" serial NOT NULL,
  "user_id" varchar(255) NOT NULL,
  "timestamp" TIMESTAMP NOT NULL,
  "content" TEXT,
  "regrade_id" VARCHAR(255) NOT NULL,
  "deleted" BOOLEAN default false NOT NULL,
  thread_id INTEGER DEFAULT 0 NOT NULL
)
""")

        cursor.execute("""ALTER TABLE "regrade_discussion" ADD CONSTRAINT "regrade_discussion_regrade_requests_id_fk" FOREIGN KEY ("thread_id") REFERENCES "regrade_requests"("id")""")


def down(conn):
    with conn.cursor() as cursor:
        cursor.execute("DROP TABLE regrade_discussion")
        cursor.execute("DROP TABLE regrade_requests")
