import psycopg2
import psycopg2.extras
import csv
import sys
import time
import re
from timeit import default_timer as timer

conn_string = "host='db_example_postgres' dbname='contributors' user='contributors_reader' password='contributors_reader'"

def err(message):
    print(message, file=sys.stderr)

def log_error(message):
    with open('connection_errors.txt', 'a') as log_file:
        log_file.write(message)
        log_file.write('\n')

def log_query_metrics(message):
    with open('query_metrics.txt', 'a') as log_file:
        log_file.write(message + '\n')

def get_connection():
    count = 0
    conn = None
    t = timer()
    while not conn and count < 120:
        count += 1
        try:
            conn = psycopg2.connect(conn_string)
        except psycopg2.OperationalError as op_error:
            time.sleep(0.25)

    if conn:
        elapsed = timer() - t
        log_query_metrics(f"Connection established after {elapsed:.3f} seconds")
    else:
        err("Could not establish connection")
    return conn

def get_cursor(conn):
    t = timer()
    cursor = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    log_query_metrics(f"Cursor get in {(timer() - t):.3f} seconds")
    return cursor


def run_query(cursor, query):
    cursor.execute(query)
    records = cursor.fetchall()
    return records

def run_cost(cursor, query):
    cost_query = "EXPLAIN ANALYZE " + query
    cursor.execute(cost_query)
    records = cursor.fetchone()
    if len(records) == 0:
        return 0
    cost = re.search('(cost=)([0-9]+.\d\d)', records[0])
    time = re.search('(time=)([0-9]+.\d\d\d)', records[0])
    return float(cost[2]), float(time[2])


def load_query(path):
    with open(path, 'r') as query_file:
        contents = query_file.readlines()
        lines = map(lambda bytes: str(bytes), contents)
        no_comments = filter(lambda line: not line.lstrip().startswith('--'), lines)
        no_bom = list(map(lambda line: line.replace('\xef\xbb\xbf', ''), no_comments))
        joined = ''.join(no_bom)
        split = joined.split(';')
        return split[0]

def main():
    conn = get_connection()
    if conn == None:
        err('Connection failed')
        return
    curr = get_cursor(conn)
    for num in range(1, 4):
        try:
            input_file = str(num) + ".sql"
            output_file = str(num) + "-result.txt"

            query = load_query(input_file)
            result = run_query(curr, query)
            cost, time = run_cost(curr, query)
            conn.commit()
            log_query_metrics(f"Query {num:d} run in {time:.3f} sec with cost {cost:.2f}")

            with open(output_file, 'w', newline='', encoding='utf-8') as result_file:
                writer = csv.writer(result_file, lineterminator='\n')
                writer.writerow([attr for attr in result[0].keys()])
                for t in result:
                    writer.writerow(t)
        except:
            conn.rollback()
            err("Solution for %d raised exception %s" % (num, sys.exc_info()))


if __name__ == '__main__':
    main()
