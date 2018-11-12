def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable_data ADD COLUMN gd_user_annotation_viewed_date timestamp(6) with time zone DEFAULT NULL;")


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
            cursor.execute("ALTER TABLE gradeable_data DROP COLUMN eg_regrade_request_date;")
