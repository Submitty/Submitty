#!/usr/bin/env python3

import smtplib
import json
import os
import sys
import time
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

#gets the email list for a class
def getClassList(semester, course):
	db_name = "submitty_{}_{}".format(semester, course)
	 # If using a UNIX socket, have to specify a slightly different connection string
	if os.path.isdir(DB_HOST):
		conn_string = "postgresql://{}:{}@/{}?host={}".format(DB_USER, DB_PASSWORD, db_name, DB_HOST)
	else:
		conn_string = "postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASSWORD, DB_HOST, db_name)

	engine = create_engine(conn_string)
	db = engine.connect()
	metadata = MetaData(bind=db)

	student_emails = []
	result = db.execute("SELECT user_email FROM users WHERE user_group != 4 OR registration_section IS NOT null;")
	for email in result:
		student_emails.append(email[0])

	return student_emails


def constructMailString(send_to, subject, body):
	return "TO:%s\nFrom: %s\nSubject:  %s \n\n\n %s \n\n" %(send_to, EMAIL_SENDER, subject, body)

def constructAnnouncementEmail(student_email, thread_title, thread_content):
	body = "Your Intructor Posted a Note\n" + thread_content 
	mail_string = constructMailString(student_email, thread_title, thread_content)
	return mail_string

def sendAnnouncement():
	mail_client = constructMailClient()

	if(len(sys.argv) < 6):
		print("Error: insufficient arguments given - Usage: python3 sendEmail.py {email_type} {semester} {course} {title} {body}")
		exit(-1) 

	#TODO: check arguments length 
	semester = sys.argv[2]
	course = sys.argv[3]
	thread_title = sys.argv[4]
	thread_content = sys.argv[5]
	print("Attempting to Send an Email Announcement. Course: {}, Semester: {}, Announcement Title: {}".format(course, semester, thread_title))

	class_list = getClassList(semester, course)
	emailCount = 0 
	for student_email in class_list:
		announcement_email = constructMailString(student_email, thread_title, thread_content)
		mail_client.sendmail(EMAIL_SENDER, student_email, announcement_email)

		#Sleep if we reach a certain sending threshold
		#TODO: bring this in via config. Might be different depending on the mail service being used
		emailCount += 1 
		if(emailCount % 100 == 0):
			time.sleep(65)

	print("Sucessfully Emailed an Announcement to {} Students".format(emailCount))

def main():
	try:
		#grab arguments and figure out mail type
		if len(sys.argv) < 2:
			print("Error: email type not given to to email_script")
			return 
		
		email_type = sys.argv[1]

		if email_type == 'announce':
			sendAnnouncement()

	except Exception as e:
		print("Error: " + str(e))


if __name__ == "__main__":
    main()
