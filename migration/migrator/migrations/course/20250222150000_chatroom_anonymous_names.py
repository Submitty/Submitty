"""Migration for a given Submitty course database."""

def up(config, database, semester, course):
    # Check if table already exists to avoid duplicate table error
    result = database.execute("""
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'chatroom_anonymous_names'
        );
    """)
    
    if not result.scalar():
        database.execute("""
        CREATE TABLE chatroom_anonymous_names (
            chatroom_id integer NOT NULL,
            user_id character varying NOT NULL,
            display_name character varying(50) NOT NULL,
            created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (chatroom_id, user_id)
        );
        
        CREATE UNIQUE INDEX idx_chatroom_anon_names_display ON chatroom_anonymous_names(chatroom_id, display_name);
        """)

def down(config, database, semester, course):
    database.execute("DROP TABLE IF EXISTS chatroom_anonymous_names;")
