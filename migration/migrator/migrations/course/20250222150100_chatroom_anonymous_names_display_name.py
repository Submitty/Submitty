"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    # Only run migration if we have the old schema (anon_id exists and display_name doesn't)
    display_name_exists = database.table_has_column("chatroom_anonymous_names", "display_name")
    if not display_name_exists:
        anon_id_exists = database.table_has_column("chatroom_anonymous_names", "anon_id")
        if anon_id_exists:
            database.execute("""
                ALTER TABLE chatroom_anonymous_names ADD COLUMN display_name character varying(50);
                UPDATE chatroom_anonymous_names SET display_name = 'Anonymous Mysterious Sparrow #' || RIGHT(anon_id, 4);
                ALTER TABLE chatroom_anonymous_names ALTER COLUMN display_name SET NOT NULL;
                ALTER TABLE chatroom_anonymous_names DROP COLUMN anon_id;
            """)


def down(config, database, semester, course):
    display_name_exists = database.table_has_column("chatroom_anonymous_names", "display_name")
    if display_name_exists:
        database.execute("""
            ALTER TABLE chatroom_anonymous_names ADD COLUMN anon_id character varying(32);
            UPDATE chatroom_anonymous_names SET anon_id = SUBSTRING(display_name FROM '#([A-Fa-f0-9]{4})$');
            ALTER TABLE chatroom_anonymous_names ALTER COLUMN anon_id SET NOT NULL;
            ALTER TABLE chatroom_anonymous_names DROP COLUMN display_name;
        """)
