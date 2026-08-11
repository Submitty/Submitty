#!/usr/bin/env python3

import sys
import os
import argparse
import traceback
from sqlalchemy.exc import SQLAlchemyError

import clustering_database_queries
from algorithms.dummy_split import DummySplit


def main():
    parser = argparse.ArgumentParser(description="Run clustering algorithm for a gradeable")
    parser.add_argument("semester", help="The semester of the course")
    parser.add_argument("course", help="The course name")
    parser.add_argument("gradeable_id", help="The gradeable ID")
    parser.add_argument("algorithm", choices=["dummy_split", "custom_upload"],
                        help="The clustering algorithm to run")
    parser.add_argument("--script-path", default="",
                        help="Path to the custom clustering script (required for custom_upload)")
    parser.add_argument("--docker-image", default="",
                        help="Docker image to run the custom clustering script in (required for custom_upload)")

    args = parser.parse_args()

    # Setup DB connection for this specific course
    db_name = f"submitty_{args.semester}_{args.course}"

    try:
        course_conn = clustering_database_queries.setup_course_db(db_name)
    except SQLAlchemyError as e:
        print(f"Error connecting to course database {db_name}: {e}")
        traceback.print_exc()
        sys.exit(1)

    try:
        # Fetch submitters
        submitters = clustering_database_queries.get_active_submitters(
            course_conn, args.gradeable_id
        )

        if args.algorithm == 'dummy_split':
            algo = DummySplit()
            cluster_groups = algo.run(submitters)
        elif args.algorithm == 'custom_upload':
            if not args.script_path:
                raise ValueError("--script-path is required for custom_upload algorithm")
            if not os.path.isfile(args.script_path):
                raise FileNotFoundError(f"Custom script not found: {args.script_path}")
            if not args.docker_image:
                raise ValueError("--docker-image is required for custom_upload algorithm")
            # Imported lazily so that dummy_split does not require the docker package.
            from container_execution import execute_custom_clustering
            input_data = {
                'submitters': submitters,
                'gradeable_id': args.gradeable_id
            }
            cluster_groups = execute_custom_clustering(args.script_path, input_data, args.docker_image)
        else:
            raise ValueError(f"Unknown algorithm: {args.algorithm}")

        clustering_database_queries.bulk_insert_clustering(
            course_conn, args.gradeable_id, args.algorithm, cluster_groups
        )

        print(f"Successfully ran {args.algorithm} clustering for {args.gradeable_id}")
    except SQLAlchemyError as e:
        print(f"Database error while generating clusters: {e}", file=sys.stderr)
        traceback.print_exc()
        sys.exit(1)
    except (RuntimeError, ValueError, FileNotFoundError) as e:
        # These carry messages written for the grader and are shown in the UI.
        print(str(e), file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Unexpected error while generating clusters: {e}", file=sys.stderr)
        traceback.print_exc()
        sys.exit(1)
    finally:
        course_conn.close()


if __name__ == "__main__":
    main()
