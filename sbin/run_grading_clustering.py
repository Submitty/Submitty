#!/usr/bin/env python3

import sys
import os
import argparse
from sqlalchemy import create_engine
from sqlalchemy.exc import SQLAlchemyError

import database_queries
from clustering_algorithms import dummy_split

def main():
    parser = argparse.ArgumentParser(description="Run clustering algorithm for a gradeable")
    parser.add_argument("semester", help="The semester of the course")
    parser.add_argument("course", help="The course name")
    parser.add_argument("gradeable_id", help="The gradeable ID")
    parser.add_argument("algorithm", help="The clustering algorithm to run")
    
    args = parser.parse_args()
    
    # Setup DB connection for this specific course
    db_name = f"submitty_{args.semester}_{args.course}"
    db_user = database_queries.DB_USER
    db_pass = database_queries.DB_PASSWORD
    db_host = database_queries.DB_HOST
    
    if os.path.isdir(db_host):
        conn_string = f"postgresql://{db_user}:{db_pass}@/{db_name}?host={db_host}"
    else:
        conn_string = f"postgresql://{db_user}:{db_pass}@{db_host}/{db_name}"

    try:
        engine = create_engine(conn_string)
        course_conn = engine.connect()
    except SQLAlchemyError as e:
        print(f"Error connecting to course database {db_name}: {e}")
        sys.exit(1)
        
    try:
        # Fetch submitters
        submitters = database_queries.get_active_submitters(course_conn, args.gradeable_id)
        
        if args.algorithm == 'dummy_split':
            cluster_groups = dummy_split.run(submitters)
        else:
            print(f"Unknown algorithm: {args.algorithm}")
            sys.exit(1)
            
        database_queries.bulk_insert_clustering(course_conn, args.gradeable_id, args.algorithm, cluster_groups)
        
        print(f"Successfully ran {args.algorithm} clustering for {args.gradeable_id}")
    except Exception as e:
        print(f"Error generating clusters: {e}")
        sys.exit(1)
    finally:
        course_conn.close()

if __name__ == "__main__":
    main()
