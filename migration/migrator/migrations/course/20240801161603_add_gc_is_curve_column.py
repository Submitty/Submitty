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
   database.execute("ALTER TABLE gradeable_component ADD COLUMN IF NOT EXISTS gc_is_curve boolean NOT NULL DEFAULT FALSE")




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
   """
   database.execute("ALTER TABLE gradeable_component DROP COLUMN gc_is_curve")
