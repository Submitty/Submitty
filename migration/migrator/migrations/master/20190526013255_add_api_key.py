"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    if not database.table_has_column("users", "api_key"):
    
        database.execute("CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;")

        database.execute("ALTER TABLE users ADD COLUMN api_key character varying(255) NOT NULL UNIQUE DEFAULT encode(gen_random_bytes(16), 'hex');")

        database.execute("""
CREATE OR REPLACE FUNCTION generate_api_key() 
RETURNS TRIGGER AS $generate_api_key$
-- TRIGGER function to generate api_key on INSERT or UPDATE of user_password in
-- table users.
BEGIN
  NEW.api_key := encode(gen_random_bytes(16), 'hex');
  RETURN NEW;
END;
$generate_api_key$ LANGUAGE plpgsql;
""")

        database.execute("""
DROP TRIGGER IF EXISTS generate_api_key ON users;
""")

        database.execute("""
CREATE TRIGGER generate_api_key 
BEFORE INSERT OR UPDATE OF user_password ON users
FOR EACH ROW
EXECUTE PROCEDURE generate_api_key();
""")


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
