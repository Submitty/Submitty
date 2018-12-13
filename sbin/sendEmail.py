#!/usr/bin/env python3

import smtplib
import json
import sys
import os
import time
import datetime
from sqlalchemy import create_engine, Table, MetaData

with open(os.path.join("/usr/local/submitty/config", 'database.json')) as open_file:
    OPEN_JSON = json.load(open_file)

EMAIL_USER = OPEN_JSON['email_user']
EMAIL_PASSWORD = OPEN_JSON['email_password']
EMAIL_SENDER = OPEN_JSON['email_sender']
EMAIL_HOSTNAME = OPEN_JSON['email_server_hostname']
EMAIL_PORT = int(OPEN_JSON['email_server_port'])
DB_HOST = OPEN_JSON['database_host']
DB_USER = OPEN_JSON['database_user']
DB_PASSWORD = OPEN_JSON['database_password']

today = datetime.datetime.now()
log_path = "/var/local/submitty/logs/emails"
logfile = open(os.path.join(log_path, (str(today.year) + str(today.month) + str(today.day) + ".txt")), 'a') 

def setupDB():
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

db = setupDB()

#configures a mail client to send email 
def constructMailClient():
	try:
		#TODO: change hostname for smtp server to a domain name
		client = smtplib.SMTP(EMAIL_HOSTNAME, EMAIL_PORT)
		client.starttls() 
		client.ehlo() 
		client.login(EMAIL_USER, EMAIL_PASSWORD)
	except:
		print("Error: connection to mail server failed. check mail config")
		exit(-1) 
	return client 

#gets queue of emails waiting to be sent
def getEmailQueue():
	#TODO: set limit in config
	result = db.execute("SELECT * FROM emails WHERE sent=FALSE ORDER BY id LIMIT 100;")
	queuedEmails = []
	for row in result:
		emailData = {}
		emailData["id"] = row[0]
		emailData["send_to"] = row[1]
		emailData["subject"] = row[2]
		emailData["body"] = row[3]
		emailData["metadata"] = {}

		queuedEmails.append(emailData)

	return queuedEmails

def markSent(email_id):
	queryString = "UPDATE emails SET sent = TRUE WHERE id = {};".format(email_id) 
	result = db.execute(queryString)

def constructMailString(send_to, subject, body):
	return "TO:%s\nFrom: %s\nSubject:  %s \n\n\n %s \n\n" %(send_to, EMAIL_SENDER, subject, body)

def sendEmail():
	queuedEmails = getEmailQueue()
	mailClient = constructMailClient()

	sentCount = 0 
	for emailData in queuedEmails:
		email = constructMailString(emailData["send_to"], emailData["subject"], emailData["body"])
		# mail_client.sendmail(EMAIL_SENDER, emailData["send_to"], email)
		markSent(emailData["id"])
		sentCount += 1

	logfile.write("Sucessfully Emailed {} Users".format(sentCount))

def main():
	try:
		sendEmail()
	except Exception as e:
		logfile.write("Error: {}".format(str(e)))

if __name__ == "__main__":
    main()
