"""
user_email is now an optional field (but still non NULL).
store user_id instead of just the email address.
"""


def up(config, database):

    # user_email is now an optional field in the users table
    database.execute("ALTER TABLE users ALTER COLUMN user_email TYPE character varying, ALTER COLUMN user_email SET NOT NULL")

    # add an error column to the emails table
    database.execute("ALTER TABLE emails ADD COLUMN IF NOT EXISTS error CHARACTER VARYING NOT NULL DEFAULT ''")

    # add a user_id column to the emails table
    database.execute("ALTER TABLE emails ADD COLUMN IF NOT EXISTS user_id CHARACTER VARYING")

    # find a match (if it exists) from the users table to attempt to fillin the user_name for each prior email
    # note: if there are multiple usernames, it seems to pick one of them (without causing an error)
    # note: if there are no matches, it leaves it null
    database.execute("UPDATE emails SET user_id=subquery.user_id FROM (SELECT DISTINCT ON(user_email) user_id, user_email FROM users) AS subquery WHERE emails.recipient != '' and emails.recipient = subquery.user_email")

    # throw out any emails that we were unable to match with a username
    database.execute("DELETE FROM emails WHERE user_id IS NULL or user_id=''")

    # now insiste that the user_id must be non null
    database.execute("ALTER TABLE ONLY emails ALTER COLUMN user_id SET NOT NULL")

    # we could drop the recipient column, but leaving it here for legacy code (and in case we rollback and forward again)
    # we can delete it later...
    #   ALTER TABLE emails DROP COLUMN recipient;

    # and make sure it exists in the users table
    database.execute("ALTER TABLE emails ADD CONSTRAINT emails_user_id_fk FOREIGN KEY (user_id) REFERENCES users (user_id)")

    pass


def down(config, database):

    database.execute("ALTER TABLE emails DROP CONSTRAINT if exists emails_user_id_fk")

    pass
