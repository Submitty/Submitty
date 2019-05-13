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
from sqlalchemy import create_engine, MetaData

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')

    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        CONFIG = json.load(open_file)

    EMAIL_USER = CONFIG.get('email_user', None)
    EMAIL_PASSWORD = CONFIG.get('email_password', None)
    EMAIL_SENDER = CONFIG['email_sender']
    EMAIL_HOSTNAME = CONFIG['email_server_hostname']
    EMAIL_PORT = int(CONFIG['email_server_port'])
    EMAIL_REPLY_TO = CONFIG['email_reply_to']
    EMAIL_LOG_PATH = CONFIG["email_logs_path"]

    DB_HOST = CONFIG['database_host']
    DB_USER = CONFIG['database_user']
    DB_PASSWORD = CONFIG['database_password']

    TODAY = datetime.datetime.now()
    LOG_FILE = open(os.path.join(
        EMAIL_LOG_PATH, "{}{}{}.txt".format(TODAY.year, TODAY.month, TODAY.day)), 'a')
except Exception as config_fail_error:
    print("[{}] Error: Email/Database Configuration Failed {}".format(
        str(datetime.datetime.now()), str(config_fail_error)))
    exit(-1)


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
    MetaData(bind=db)
    return db


def construct_mail_client():
    """Authenticate with an SMTP server and return a reference to the connection."""
    client = smtplib.SMTP(EMAIL_HOSTNAME, EMAIL_PORT)
    # attempt to use TLS for connection, but don't require it
    try:
        client.starttls()
    except smtplib.SMTPNotSupportedError:
        pass
    client.ehlo()

    if EMAIL_USER is not None:
        client.login(EMAIL_USER, EMAIL_PASSWORD)

    return client


def get_email_queue(db):
    """Get an active queue of emails waiting to be sent."""
    result = db.execute(
        "SELECT * FROM emails WHERE sent IS NULL ORDER BY id LIMIT 100;")

    queued_emails = []
    for row in result:
        queued_emails.append({
            'id': row[0],
            'send_to': row[1],
            'subject': row[2],
            'body': row[3]
            })

    return queued_emails


def mark_sent(email_id, db):
    """Mark an email as sent in the database."""
    query_string = "UPDATE emails SET sent=NOW() WHERE id = {};".format(email_id)
    db.execute(query_string)


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
    db = setup_db()
    queued_emails = get_email_queue(db)
    mail_client = construct_mail_client()

    if len(queued_emails) == 0:
        return

    for email_data in queued_emails:
        email = construct_mail_string(
            email_data["send_to"], email_data["subject"], email_data["body"])
        mail_client.sendmail(EMAIL_SENDER, email_data["send_to"], email.encode('utf8'))
        mark_sent(email_data["id"], db)

    LOG_FILE.write("[{}] Sucessfully Emailed {} Users\n".format(
        str(datetime.datetime.now()), len(queued_emails)))


def main():
    """Send queued Submitty emails and log any errors."""
    try:
        send_email()
    except Exception as email_send_error:
        LOG_FILE.write("[{}] Error Sending Email: {}\n".format(
            str(datetime.datetime.now()), str(email_send_error)))


if __name__ == "__main__":
    main()
