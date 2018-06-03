migrator
========

Internal tool of Submitty to handle upgrading the DB and system in a seamless way to
users. The tool contains three migration folders:
* master - migrations pertaining to changing the master submitty DB
* course - migrations pertaining to changing each course DB
* system - migrations pertaining to altering the system submitty runs on

Usage
-----
```
usage: migrator.py [-h] [--environment {course,system,master}] --config
                   CONFIG_PATH
                   command ...

Migration script for upgrading/downgrading the database

positional arguments:
  command
    create              Create migration
    migrate             Run migrations
    rollback            Rollback the previously done migration

optional arguments:
  -h, --help            show this help message and exit
  --environment {course,system,master}, -e {course,system,master}
  --config CONFIG_PATH, -c CONFIG_PATH
 ```
 
By default, `migrator.py` will look for a config directory two directories up 
from where it's run (`../../config`) and if found, you don't need to provide
an argument to `-c`, else you will have to provide one. The environment
defaults to selecting all three (`course`, `system`, `master`), but you can
select any mix/match, by using any number of `-e` calls (ex: `-e system -e master`).
 
The tool has three commands:
* create (creates a migration in the given environment folder)
* migrate (runs migrations for a given environment)
* rollback (reverse the last run migration for a given environment)
 
Primarily, one expects to primarily use just `create` and `migrate`, use the `-h` flag
to see more information about them.
 
When running `migrate` or `rollback`, `migrator.py` will load all migrations in the
select environment folder (under `migrations/`) into memory, and then make a select
against the DB to get the status of the migrations. For any migration that is in
the DB, but not in the folder, it will attempt to see if the migration exists
in `${SUBMITTY_INSTALL_DIR}/migrations` and if so, will run the `down` function
on each missing migration, and then delete the record of it from the DB.
 
`migrate` accepts two flags: `--fake` and `--initial`. Fake will mark all migrations
as being run, without actually running them. `--initial` 
