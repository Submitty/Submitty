#!/usr/bin/env python3

"""
Handles general sending of emails in Submitty.

This is done by polling the emails table for queued emails and
properly formatting/sending an email programmatically.
"""

import smtplib
import json
import os
import datetime
from sqlalchemy import create_engine, MetaData, Table, bindparam, text
import sys
import psutil


# ======================================================================
#
# Let's make sure we're the only copy of this script running on the
# server.  Multiple copies might happen if sending emails is slow or
# hangs and takes longer than 1 minute and the cron job fires again.
#

# We could just match the program name, but this is problematic if
# happens to match the filename submitted by a student.
# my_program_name = sys.argv[0].split('/')[-1]

# So instead let's match the full path used in the cron script
my_program_name = sys.argv[0]

my_pid = os.getpid()

# loop over all active processes on the server
for p in psutil.pids():
    try:
        cmdline = psutil.Process(p).cmdline()
        if (len(cmdline) < 2):
            continue
        # if anything on the command line matches the name of the program
        if cmdline[0].find("python") != -1 and cmdline[1].find(my_program_name) != -1:
            if p != my_pid:
                print("ERROR!  Another copy of '" + my_program_name +
                      "' is already running on the server.  Exiting.")
                sys.exit(1)
    except psutil.NoSuchProcess:
        # Whoops, the process ended before we could look at it.
        # But that's ok!
        pass

# ======================================================================

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')

    with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
        SUBMITTY_CONFIG = json.load(open_file)

    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        DATABASE_CONFIG = json.load(open_file)

except Exception as config_fail_error:
    print("[{}] ERROR: CORE SUBMITTY CONFIGURATION ERROR {}".format(
        str(datetime.datetime.now()), str(config_fail_error)))
    sys.exit(1)


DATA_DIR_PATH = SUBMITTY_CONFIG['submitty_data_dir']
EMAIL_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "emails")
TODAY = datetime.datetime.now()
LOG_FILE = open(os.path.join(
    EMAIL_LOG_PATH, "{:04d}{:02d}{:02d}.txt".format(TODAY.year, TODAY.month,
                                                    TODAY.day)), 'a')


try:
    with open(os.path.join(CONFIG_PATH, 'email.json')) as open_file:
        EMAIL_CONFIG = json.load(open_file)
    EMAIL_ENABLED = EMAIL_CONFIG.get('email_enabled', False)
    EMAIL_USER = EMAIL_CONFIG.get('email_user', '')
    EMAIL_PASSWORD = EMAIL_CONFIG.get('email_password', '')
    EMAIL_SENDER = EMAIL_CONFIG['email_sender']
    EMAIL_HOSTNAME = EMAIL_CONFIG['email_server_hostname']
    EMAIL_PORT = int(EMAIL_CONFIG['email_server_port'])
    EMAIL_REPLY_TO = EMAIL_CONFIG['email_reply_to']
    EMAIL_INTERNAL_DOMAIN = EMAIL_CONFIG['email_internal_domain']

    DB_HOST = DATABASE_CONFIG['database_host']
    DB_USER = DATABASE_CONFIG['database_user']
    DB_PASSWORD = DATABASE_CONFIG['database_password']

except Exception as config_fail_error:
    e = "[{}] ERROR: Email/Database Configuration Failed {}".format(
        str(datetime.datetime.now()), str(config_fail_error))
    LOG_FILE.write(e+"\n")
    print(e)
    sys.exit(1)


def setup_db():
    """Set up a connection with the submitty database."""
    db_name = "submitty"
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(
            DB_USER, DB_PASSWORD, db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(
            DB_USER, DB_PASSWORD, DB_HOST, db_name)

    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData(bind=db)
    return db, metadata


def construct_mail_client():
    """Authenticate with an SMTP server and return a reference to the connection."""
    client = smtplib.SMTP(EMAIL_HOSTNAME, EMAIL_PORT)
    # attempt to use TLS for connection, but don't require it
    try:
        client.starttls()
    except smtplib.SMTPNotSupportedError:
        pass
    client.ehlo()

    if EMAIL_USER != '' and EMAIL_PASSWORD != '':
        client.login(EMAIL_USER, EMAIL_PASSWORD)

    return client


def get_email_queue(db):
    """Get an active queue of internal emails waiting to be sent."""
    query = """SELECT id, user_id, email_address, subject, body FROM emails
    WHERE email_address SIMILAR TO :format AND sent is NULL AND
    error = '' ORDER BY id LIMIT 100;"""
    domain_format = '%@(%.' + EMAIL_INTERNAL_DOMAIN + '|' + EMAIL_INTERNAL_DOMAIN + ')'
    result = db.execute(text(query), format = domain_format)
    queued_emails = []
    for row in result:
        queued_emails.append({
            'id': row[0],
            'user_id': row[1],
            'send_to': row[2],
            'subject': row[3],
            'body': row[4]
            })

    return queued_emails


def get_external_queue(db, num):
    """Get an active queue of external emails waiting to be sent."""
    query = """SELECT COUNT(*) FROM emails WHERE sent >= (NOW() - INTERVAL '1 hour') AND
    email_address NOT SIMILAR TO :format"""
    domain_format = '%@(%.' + EMAIL_INTERNAL_DOMAIN + '|' + EMAIL_INTERNAL_DOMAIN + ')'
    result = db.execute(text(query), format = domain_format)
    query = """SELECT id, user_id, email_address, subject, body FROM emails
    WHERE sent is NULL AND email_address NOT SIMILAR TO :format AND
    error = '' ORDER BY id LIMIT :lim;"""
    result = db.execute(text(query), format = domain_format, lim = min(500-int(result.fetchone()[0]), num))
    queued_emails = []
    for row in result:
        queued_emails.append({
            'id': row[0],
            'user_id': row[1],
            'send_to': row[2],
            'subject': row[3],
            'body': row[4]
            })
    return queued_emails


def mark_sent(email_id, db):
    """Mark an email as sent in the database."""
    query_string = "UPDATE emails SET sent=NOW() WHERE id = {};".format(email_id)
    db.execute(query_string)


def store_error(email_id, db, metadata, myerror):
    """Store an error string for the specified email."""
    emails_table = Table('emails', metadata, autoload=True)
    # use bindparam to correctly handle a myerror string with single quote character
    query = emails_table.update().where(
        emails_table.c.id == email_id).values(error=bindparam('b_myerror'))
    db.execute(query, b_myerror=myerror)


def construct_mail_string(send_to, subject, body):
    """Format an email string."""
    headers = [
        ('Content-Type', 'text/plain; charset=utf-8'),
        ('TO', send_to),
        ('From', EMAIL_SENDER),
        ('reply-to', EMAIL_REPLY_TO),
        ('Subject', subject)
    ]

    msg = ''
    for header in headers:
        msg += "{}: {}\n".format(*header)

    msg += "\n\n{}\n\n".format(body)
    return msg


def send_email():
    """Send queued emails."""
    db, metadata = setup_db()
    queued_emails = get_email_queue(db)
    mail_client = construct_mail_client()

    success_count = 0

    if "'" in EMAIL_INTERNAL_DOMAIN:
        return

    queued_emails = queued_emails + get_external_queue(db, 100-len(queued_emails))

    if len(queued_emails) == 0:
        return

    for email_data in queued_emails:
        if email_data["send_to"] == "":
            store_error(email_data["id"], db, metadata, "WARNING: empty email address")
            e = "[{}] WARNING: empty email address for recipient {}".format(
                str(datetime.datetime.now()), email_data["user_id"])
            LOG_FILE.write(e+"\n")
            continue

        email = construct_mail_string(
            email_data["send_to"], email_data["subject"], email_data["body"])

        try:
            mail_client.sendmail(EMAIL_SENDER,
                                 email_data["send_to"], email.encode('utf8'))
            mark_sent(email_data["id"], db)
            success_count += 1

        except Exception as email_send_error:
            store_error(email_data["id"], db, metadata, "ERROR: sending email "
                        + str(email_send_error))
            e = "[{}] ERROR: sending email to recipient {}, email {}: {}".format(
                str(datetime.datetime.now()),
                email_data["user_id"],
                email_data["send_to"],
                str(email_send_error))
            LOG_FILE.write(e+"\n")
            print(e)

    e = "[{}] Sucessfully Emailed {} Users".format(
        str(datetime.datetime.now()), success_count)
    LOG_FILE.write(e+"\n")


def main():
    if not EMAIL_ENABLED:
        return
    """Send queued Submitty emails and log any errors."""
    try:
        send_email()
    except Exception as email_send_error:
        e = "[{}] Error Sending Email: {}".format(
            str(datetime.datetime.now()), str(email_send_error))
        LOG_FILE.write(e+"\n")
        print(e)


if __name__ == "__main__":
    main()
