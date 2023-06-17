migrator
========

Internal tool of Submitty to handle upgrading the components of Submitty
in a nice seamless way.

Migrator operates under the idea that Submitty can be split into three
distinct environments:
* system - migrations pertaining to altering the system submitty runs on
* master - migrations pertaining to changing the master submitty DB
* course - migrations pertaining to changing each course DB

where each environment has its own set of migration files.

See https://submitty.org/developer/migrations for high-level developer usage documentation.

Requirements
------------
* Python 3.4+
* sqlalchemy
* psycopg2 (if using PostgreSQL)

Usage
-----
```
$ python3 run_migrator.py --help
usage: run_migrator.py [-h] [-v] -c CONFIG_PATH [-e {master,system,course}]
                       [--course semester course]
                       command ...

Migration script for upgrading/downgrading the database

positional arguments:
  command
    create              Create migration
    status              Get status of migrations for environment
    migrate             Run migrations
    rollback            Rollback the previously done migration

optional arguments:
  -h, --help            show this help message and exit
  -v, --version         show program's version number and exit
  -c CONFIG_PATH, --config CONFIG_PATH
  -e {master,system,course}, --environment {master,system,course}
  --course semester course
 ```
 
By default, `run_migrator.py` will look for a config directory two directories up 
from where it's run (`../../config`) and if found, you don't need to provide
an argument to `-c`, else you will have to provide one. The environment
defaults to selecting all three (`course`, `system`, `master`), but you can
select any mix/match, by using any number of `-e` calls (ex: `-e system -e master`).
 
The tool has three commands:
* create (creates a migration in the given environment folder)
* migrate (runs migrations for a given environment)
* rollback (reverse the last run migration for a given environment)
 
`create` takes one argument `NAME` which should be a human-readable name to give
to the migration file. The name can only contain alphanumeric characters, underscores,
and dashes (regex validation string: `[a-zA-Z0-9_\-]`). This will then create a file
for the set environment in `migration/migrations/<environment>` which has then name
structure `YYYYMMDDHHmmss_NAME.py` where the first part of the string is the current
timestmap concatentated by year, month, day, hour, minute, and second. The created
file will have an `up` and `down` function which will both have the same parameters.
The parameters are dependent on the environment you're using. System will only have
a `Config` object (see `migrator.config.Config`), Master will have a `Config` object
and a `Database` object (see `migrator.db.Database`), and Course will have a `Config`
object, `Database` object, `semester` string, and `course` string. You are not
required to have both functions in the file and it's perfectly safe and recommended
to delete the `up` or `down` function if you're not using it.

`migrate` will attempt to run any migrations that are down or haven't been run yet.
It scans the DB to get the list of migrations that have been run and their status.
It then loads all migrations from the environment's folder 
(`migration/migrations/<environment>`) at which point it matches the DB rows up with
the files. For any file that doesn't have a corresponding DB row, or does have a DB
row, but the status is down, will be run, having its `up` function run (assuming it
exists). After the migration is successfully run, the DB is updated to reflect that,
and then `run_migrator.py` moves on to the next migration.

`rollback` will attempt to reverse the last run migration for a given environment.
It scans the DB, getting all of the migrations that have been run. It the matches
that up against the migration files from the environment's folder, and then takes
the migration that has an up status and latest id, and runs its `down` function and
then sets its status in the DB as down. It will then not do anything else with any
of other migrations.

When running `migrate` or `rollback`, `run_migrator.py` will load all migrations in the
select environment folder (under `migrations/`) into memory, and then make a select
against the DB to get the status of the migrations. For any migration that is in
the DB, but not in the folder, it will attempt to see if the migration exists
in `${SUBMITTY_INSTALL_DIR}/migrations` and if so, will run the `down` function
on each missing migration, and then delete the record of it from the DB as well as
remove that migration file from `${SUBMITTY_INSTALL_DIR}/migrations`.
 
`migrate` accepts two flags: `--fake` and `--initial`. `--fake` will mark all up migrations
as being run, without actually running them. `--initial` will only run the first migration 
(if it's down), and then force the `--fake` flag for all other up migrations. This is 
useful for first spinning up a fresh instance of Submitty as the initial migration for 
all categories contains the full up-to-date schema dump and does not require any 
transformations.

Note: When running the above commands, if a migration file is lacking an `up`/`down`
function, a no-op function is run instead that does nothing, but marks the migration
then as either migrated or rollbacked.
