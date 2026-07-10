#!/usr/bin/env python3

import sys
import argparse
import traceback
from sqlalchemy.exc import SQLAlchemyError

import database_queries
from clustering_algorithms import dummy_split


def main():
    parser = argparse.ArgumentParser(description="Run clustering algorithm for a gradeable")
    parser.add_argument("semester", help="The semester of the course")
    parser.add_argument("course", help="The course name")
    parser.add_argument("gradeable_id", help="The gradeable ID")
    parser.add_argument("algorithm", choices=["dummy_split"], help="The clustering algorithm to run")

    args = parser.parse_args()

    # Setup DB connection for this specific course
    db_name = f"submitty_{args.semester}_{args.course}"

    try:
        course_conn = database_queries.setup_course_db(db_name)
    except SQLAlchemyError as e:
        print(f"Error connecting to course database {db_name}: {e}")
        traceback.print_exc()
        sys.exit(1)

    try:
        # Fetch submitters
        submitters = database_queries.get_active_submitters(course_conn, args.gradeable_id)

        if args.algorithm == 'dummy_split':
            cluster_groups = dummy_split.run(submitters)

        database_queries.bulk_insert_clustering(
            course_conn, args.gradeable_id, args.algorithm, cluster_groups
        )

        print(f"Successfully ran {args.algorithm} clustering for {args.gradeable_id}")
    except SQLAlchemyError as e:
        print(f"Database error while generating clusters: {e}")
        traceback.print_exc()
        sys.exit(1)
    finally:
        course_conn.close()


if __name__ == "__main__":
    main()
