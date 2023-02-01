def up(config, database, term, course):
    database.execute("ALTER TABLE ONLY categories_list ADD COLUMN rank int")
    database.execute("ALTER TABLE ONLY categories_list ADD COLUMN color varchar DEFAULT '#000080' NOT NULL")


def down(config, database, term, course):
    database.execute("ALTER TABLE ONLY categories_list DROP COLUMN rank")
    database.execute("ALTER TABLE ONLY categories_list DROP COLUMN color")
