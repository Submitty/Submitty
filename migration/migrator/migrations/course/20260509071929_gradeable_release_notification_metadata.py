"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute(
        r"""
        WITH gradeable_release_notifications AS (
            SELECT
                n.id,
                COALESCE(NULLIF(n.metadata, ''), '{}')::jsonb AS metadata,
                regexp_replace(
                    n.content,
                    '^Submissions Open: (.*) \| Due .*$',
                    '\1'
                ) AS title,
                eg.eg_submission_due_date AS due_date
            FROM notifications n
            INNER JOIN electronic_gradeable eg
                ON n.gradeable_id = eg.g_id
            WHERE n.component = 'grading'
                AND n.gradeable_id IS NOT NULL
                AND n.content LIKE 'Submissions Open: % | Due %'
        )
        UPDATE notifications n
        SET metadata = (
            grn.metadata || jsonb_build_object(
                'notification_type', 'gradeable_release',
                'title', grn.title,
                'due_date', grn.due_date
            )
        )::text
        FROM gradeable_release_notifications grn
        WHERE n.id = grn.id
            AND (
                grn.metadata->>'notification_type' IS DISTINCT FROM 'gradeable_release'
                OR grn.metadata->>'title' IS NULL
                OR grn.metadata->>'due_date' IS NULL
            )
        """
    )


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    pass

