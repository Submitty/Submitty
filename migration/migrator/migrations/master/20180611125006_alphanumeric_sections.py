def up(config, database):
    database.execute('ALTER TABLE ONLY mapped_courses ALTER COLUMN registration_section SET DATA TYPE character varying(255) USING registration_section::varchar(255)')
    database.execute('ALTER TABLE ONLY mapped_courses ALTER COLUMN mapped_section SET DATA TYPE character varying(255) USING mapped_section::varchar(255)')
    database.execute('ALTER TABLE ONLY courses_users ALTER COLUMN registration_section SET DATA TYPE character varying(255) USING registration_section::varchar(255)')


def down(config, database):
    database.execute('ALTER TABLE ONLY mapped_courses ALTER COLUMN registration_section SET DATA TYPE integer USING registration_section::integer')
    database.execute('ALTER TABLE ONLY mapped_courses ALTER COLUMN mapped_section SET DATA TYPE integer USING mapped_section::integer')
    database.execute('ALTER TABLE ONLY courses_users ALTER COLUMN registration_section SET DATA TYPE integer USING registration_section::integer')
