#!/usr/bin/env python3

import smtplib
import json
import os
import datetime
from sqlalchemy import create_engine, Table, MetaData

try:
    CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        CONFIG = json.load(open_file)

    EMAIL_USER = CONFIG['email_user']
    EMAIL_PASSWORD = CONFIG['email_password']
    EMAIL_SENDER = CONFIG['email_sender']
    EMAIL_HOSTNAME = CONFIG['email_server_hostname']
    EMAIL_PORT = int(CONFIG['email_server_port'])
    EMAIL_LOG_PATH = CONFIG["email_logs_path"]

    DB_HOST = CONFIG['database_host']
    DB_USER = CONFIG['database_user']
    DB_PASSWORD = CONFIG['database_password']

    TODAY = datetime.datetime.now()
    LOG_FILE = open(os.path.join(EMAIL_LOG_PATH, "{}{}{}.txt".format(TODAY.year, TODAY.month, TODAY.day)), 'a')
except Exception as config_fail_error:
    print("[{}] Error: Email/Database Configuration Failed {}".format(str(datetime.datetime.now()),str(config_fail_error)))
    exit(-1)

def setup_db():
    db_name = "submitty"
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(DB_USER, DB_PASSWORD, db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASSWORD, DB_HOST, db_name)

    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData(bind=db)
    return db

db = setup_db()

#configures a mail client to send email 
def construct_mail_client():
    try:
        client = smtplib.SMTP(EMAIL_HOSTNAME, EMAIL_PORT)
        client.starttls()
        client.ehlo()
        client.login(EMAIL_USER, EMAIL_PASSWORD)
    except:
        print("Error: connection to mail server failed. check mail config")
        exit(-1)
    return client

#gets queue of emails waiting to be sent
def get_email_queue():
    #TODO: set limit in config
    result = db.execute("SELECT * FROM emails WHERE sent IS NULL ORDER BY id LIMIT 100;")
    queued_emails = []
    for row in result:
        email_data = {}
        email_data["id"] = row[0]
        email_data["send_to"] = row[1]
        email_data["subject"] = row[2]
        email_data["body"] = row[3]

        queued_emails.append(email_data)

    return queued_emails

#gives sent field a time stamp to indicate a sent email
def mark_sent(email_id):
    query_string = "UPDATE emails SET sent=NOW() WHERE id = {};".format(email_id)
    result = db.execute(query_string)

def construct_mail_string(send_to, subject, body):
    return "TO:%s\nFrom: %s\nSubject:  %s \n\n\n %s \n\n" %(send_to, EMAIL_SENDER, subject, body)

def send_email():
    queued_emails = get_email_queue()
    mail_client = construct_mail_client()

    if len(queued_emails) == 0:
        return

    for email_data in queued_emails:
        email = construct_mail_string(email_data["send_to"], email_data["subject"], email_data["body"])
        mail_client.sendmail(EMAIL_SENDER, email_data["send_to"], email)
        mark_sent(email_data["id"])

    LOG_FILE.write("[{}] Sucessfully Emailed {} Users\n".format(str(datetime.datetime.now()),len(queued_emails)))

def main():
    try:
        send_email()
    except Exception as email_send_error:
        LOG_FILE.write("[{}] Error: {}\n".format(str(datetime.datetime.now()),str(email_send_error)))

if __name__ == "__main__":
    main()
