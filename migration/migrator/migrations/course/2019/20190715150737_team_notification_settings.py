"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    # add team to enum notifications_component
    database.execute("ALTER TYPE notifications_component rename to notifications_component_");
    database.execute("CREATE TYPE notifications_component as enum ('forum','student','grading','team')")
    database.execute("ALTER TABLE notifications ALTER COLUMN component TYPE notifications_component USING component::text::notifications_component")
    database.execute("DROP TYPE notifications_component_")

    # add new columns to notificaiton_settings
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_invite BOOLEAN DEFAULT TRUE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_member_submission BOOLEAN DEFAULT TRUE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_joined BOOLEAN DEFAULT TRUE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_invite_email BOOLEAN DEFAULT TRUE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_member_submission_email BOOLEAN DEFAULT TRUE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS team_joined_email BOOLEAN DEFAULT TRUE NOT NULL")

def down(config, conn, semester, course):
    pass
