import argparse

from sqlalchemy import create_engine, Table, MetaData
from sqlalchemy.sql import and_

DB_HOST = "__INSTALL__FILLIN__DATABASE_HOST__"
DB_USER = "__INSTALL__FILLIN__DATABASE_USER__"
DB_PASSWORD = "__INSTALL__FILLIN__DATABASE_PASSWORD__"


def main():
    parser = argparse.ArgumentParser(description="Insert details about a version of a submission "
                                                 "into the database")
    parser.add_argument("semester", type=str, help="")
    parser.add_argument("course", type=str, help="")
    parser.add_argument("gradeable_id", type=str, help="")
    parser.add_argument("user_id", type=str, help="")
    parser.add_argument("version", type=int, help="")
    parser.add_argument("autograding_non_hidden_non_extra_credit", type=float, help="")
    parser.add_argument("autograding_non_hidden_extra_credit", type=float, help="")
    parser.add_argument("autograding_hidden_non_extra_credit", type=float, help="")
    parser.add_argument("autograding_hidden_extra_credit", type=float, help="")
    parser.add_argument("submission_time", type=str, help="")
    args = parser.parse_args()

    db_name = "submitty_{}_{}".format(args.semester, args.course)
    db = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASSWORD, DB_HOST, db_name))
    metadata = MetaData(bind=db)
    insert_table = Table('electronic_gradeable_data', metadata, autoload=True)
    db.execute(insert_table.insert(), g_id=args.gradeable_id, user_id=args.user_id,
               g_version=args.version,
               autograding_non_hidden_non_extra_credit=args.autograding_non_hidden_non_extra_credit,
               autograding_non_hidden_extra_credit=args.autograding_non_hidden_extra_credit,
               autograding_hidden_non_extra_credit=args.autograding_hidden_non_extra_credit,
               autograding_hidden_extra_credit=args.autograding_hidden_extra_credit,
               submission_time=args.submission_time)

    # if this is the first version, then we need to insert a row into the version table
    version_table = Table('electronic_gradeable_version', metadata, autoload=True)
    if args.version == 1:
        db.execute(version_table.insert(), g_id=args.gradeable_id, user_id=args.user_id,
                   active_version=args.version)
    else:
        db.execute(version_table.update(and_(version_table.c.g_id == args.gradeable_id,
                                  version_table.c.user_id == args.user_id)),
                   active_version=args.version)

if __name__ == "__main__":
    main()