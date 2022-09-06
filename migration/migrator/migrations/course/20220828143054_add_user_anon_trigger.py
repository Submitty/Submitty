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
    # random_string function taken from Szymon Lipiński's answer (https://stackoverflow.com/a/3972983)
    database.execute("""
    CREATE OR REPLACE FUNCTION random_string(length integer) RETURNS TEXT AS $$
        DECLARE
            chars text[] := '{0,1,2,3,4,5,6,7,8,9,A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z}';
            result text := '';
            i integer := 0;
        BEGIN
            IF length < 0 THEN
                raise exception 'Given length cannot be less than 0';
            END IF;
            FOR i IN 1..length LOOP
                result := result || chars[1+random()*(array_length(chars, 1)-1)];
            END LOOP;
            RETURN result;
        END;
    $$ LANGUAGE PLPGSQL;
    """)

    database.execute("""
    INSERT INTO gradeable_anon (
        SELECT user_id, g_id, random_string(15)
        FROM users u JOIN gradeable g ON 1=1 WHERE NOT EXISTS (SELECT 1 FROM gradeable_anon WHERE user_id=u.user_id AND g_id=g.g_id)
    )
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION add_course_user() RETURNS trigger AS $$
        DECLARE
            temp_row RECORD;
            random_str TEXT;
            num_rows INT;
        BEGIN
            FOR temp_row IN SELECT g_id FROM gradeable LOOP
                LOOP
                    random_str = random_string(15);
                    PERFORM 1 FROM gradeable_anon
                    WHERE g_id=temp_row.g_id AND anon_id=random_str;
                    GET DIAGNOSTICS num_rows = ROW_COUNT;
                    IF num_rows = 0 THEN
                        EXIT;
                    END IF;
                END LOOP;
                INSERT INTO gradeable_anon (
                    SELECT NEW.user_id, temp_row.g_id, random_str
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM gradeable_anon
                        WHERE user_id=NEW.user_id AND g_id=temp_row.g_id
                    )
                );
            END LOOP;
            RETURN NULL;
        END;
    $$ LANGUAGE PLPGSQL;""")

    database.execute("""
    CREATE TRIGGER add_course_user AFTER INSERT OR UPDATE ON users
        FOR EACH ROW EXECUTE PROCEDURE add_course_user();
    """)

def down(config, database, semester, course):
    """
    Run down migration (rollback).
    """
    database.execute("DROP FUNCTION IF EXISTS random_string;")
    database.execute("DROP TRIGGER IF EXISTS add_course_user ON users;")
    database.execute("DROP FUNCTION IF EXISTS add_course_user;")
