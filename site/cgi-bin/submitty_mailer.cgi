#!/usr/bin/env python3

import cgi 
# If things are not working, then this should be enabled for better troubleshooting
# cgitb.enable()
import smtplib
import json
import os
from sqlalchemy import create_engine, Table, MetaData, bindparam, select, func

with open(os.path.join("/usr/local/submitty/config", 'database.json')) as open_file:
    OPEN_JSON = json.load(open_file)

EMAIL_USER = OPEN_JSON['email_user']
EMAIL_PASSWORD = OPEN_JSON['email_password']
DB_HOST = OPEN_JSON['database_host']
DB_USER = OPEN_JSON['database_user']
DB_PASSWORD = OPEN_JSON['database_password']

print(EMAIL_USER)

#configures a mail client to send email 
def constructMailClient():
	try:
		client = smtplib.SMTP_SSL('173.194.66.109', 465)
		client.ehlo()
		client.login(EMAIL_USER, EMAIL_PASSWORD)
	except:
		print(json.dumps({"success": False, "error": True, "error_message" : "Error: connection to mail server failed. check mail config"}))
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
	result = db.execute("SELECT user_email FROM users WHERE registration_section NOTNULL;")
	for email in result:
		student_emails.append(email)

	return student_emails


def constructMailString(sent_from, sent_to, subject, body):
	return "From %s\nTo:  %s\nSubject: %s\n%s" %(sent_from, sent_to, subject, body)

def constructAnnouncementEmail(thread_title, thread_content, course, student_email):
	body = "Your Intructor Posted a Note\n" + thread_content 
	mail_string = constructMailString(EMAIL_USER, student_email, thread_title, body)
	return mail_string

def sendAnnouncement(args):
	mail_client = constructMailClient()

	if 'thread_title' not in args or 'thread_content' not in args or 'course' not in args or 'semester' not in args:
		print(json.dumps({"success": False, "error": True, "error_message" : "Error: insufficient arguments given to email_script"}))
		exit(-1)

	thread_title = str(args['thread_title'].value)
	thread_content = str(args['thread_content'].value)
	course = str(args['course'].value)
	semester = str(args['semester'].value)


	class_list = getClassList(semester, course)
	for student_email in class_list:
		announcement_email = constructAnnouncementEmail(thread_title, thread_content, course, student_email)
		mail_client.sendmail(EMAIL_USER, student_email, announcement_email)

def main():

	try:
		#grab arguments and figure out mail type
		args = cgi.FieldStorage()

		if 'email_type' not in args:
			print(json.dumps({"success": False, "error": True, "error_message" : "Error: email type not given to to email_script"}))
			return 

		email_type = str(args['email_type'].value)

		if email_type == 'announce':
			sendAnnouncement(args)

		print(json.dumps({"success": True, "error": False}))
	except:
		print(json.dumps({"success": False, "error": True, "error_message" : "an unexpected error occured"}))


if __name__ == "__main__":
    main()

