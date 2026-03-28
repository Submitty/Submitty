"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    database.execute('''
        CREATE TABLE IF NOT EXISTS public.login_attempts (
            id             SERIAL PRIMARY KEY,
            user_id        VARCHAR(255) NOT NULL,
            ip_address     VARCHAR(45)  NOT NULL,
            attempted_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            was_successful BOOLEAN      NOT NULL DEFAULT FALSE
        )
    ''')

    database.execute('''
        CREATE INDEX IF NOT EXISTS idx_login_attempts_user
            ON public.login_attempts (user_id, attempted_at)
    ''')

    database.execute('''
        CREATE INDEX IF NOT EXISTS idx_login_attempts_ip
            ON public.login_attempts (ip_address, attempted_at)
    ''')


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    database.execute('DROP INDEX IF EXISTS public.idx_login_attempts_ip')
    database.execute('DROP INDEX IF EXISTS public.idx_login_attempts_user')
    database.execute('DROP TABLE IF EXISTS public.login_attempts')
