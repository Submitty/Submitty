def up(config, database, semester, course):
    if not database.table_has_column('electronic_gradeable', 'eg_regrade_request_date'):
        database.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")

    if not database.table_has_column('electronic_gradeable', 'eg_regrade_allowed'):
        database.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_allowed boolean DEFAULT TRUE NOT NULL")

    database.execute("UPDATE electronic_gradeable SET eg_regrade_request_date='9999-08-02' WHERE eg_regrade_request_date IS NULL")
    database.execute("ALTER TABLE electronic_gradeable ALTER COLUMN eg_regrade_request_date SET NOT NULL")

def down(config, database, semester, course):
    database.execute("ALTER TABLE electronic_gradeable ALTER COLUMN eg_regrade_request_date DROP NOT NULL")
