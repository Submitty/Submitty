"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
    """
    CREATE TABLE IF NOT EXISTS saml_mapped_users (
        id serial NOT NULL PRIMARY KEY,
        saml_id varchar(255) NOT NULL,
        user_id varchar(255) NOT NULL,
        active boolean NOT NULL DEFAULT TRUE,
        CONSTRAINT fk_user_id
            FOREIGN KEY(user_id)
                REFERENCES users(user_id),
        UNIQUE(saml_id, user_id)
    );
    """
    )

    database.execute(
    """
    CREATE OR REPLACE FUNCTION saml_mapping_check() RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF (SELECT count(*) FROM saml_mapped_users WHERE NEW.user_id = user_id) = 1
            THEN
                IF (SELECT count(*) FROM saml_mapped_users WHERE NEW.user_id = user_id AND user_id = saml_id) = 1
                THEN
                    RAISE EXCEPTION 'SAML mapping already exists for this user';
                end if;
                IF NEW.user_id = NEW.saml_id
                THEN
                    RAISE EXCEPTION 'Cannot create SAML mapping for proxy user';
                end if;
            end if;
            RETURN NEW;
        END;
        $$;
    """
    )

    database.execute(
    """
    CREATE TRIGGER saml_mapping_check_trigger BEFORE INSERT OR UPDATE on public.saml_mapped_users
    FOR EACH ROW EXECUTE PROCEDURE saml_mapping_check();
    """
    )


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("DROP TRIGGER IF EXISTS saml_mapping_check_trigger;")
    database.execute("DROP FUNCTION IF EXISTS saml_mapping_check;")
