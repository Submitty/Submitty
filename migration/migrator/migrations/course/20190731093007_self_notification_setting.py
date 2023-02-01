"""Migration for a given Submitty course database."""


def up(config, database, term, course):
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS self_notification BOOLEAN DEFAULT FALSE NOT NULL");
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS self_notification_email BOOLEAN DEFAULT FALSE NOT NULL");


def down(config, database, term, course):
    pass
