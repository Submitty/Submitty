#!/usr/bin/env python3 
import sys
import os
import csv

# Given a directory mirroring a submission directory (see arguments), this script will generate 
# a csv userlist for upload to submitty.
#
# ARGUMENTS
# 1) The path to the top level of the old assignment's directory tree. This folder's subdirectories should mirror 
#    a /var/local/submitty/courses/<semester>/<course>/submissions/<assignment_name> folder. 
# OUTPUT:
# 1) A csv of the form: username, LastName, FirstName, email, RegistrationSection
def main():
  if len(sys.argv) < 2:
    print("ERROR: Please enter the directory that you wish to traverse for student names.")
    sys.exit(1)
  user_dir = sys.argv[1]
  if not os.path.isdir(user_dir):
    print("The provided directory (" + user_dir + ") does not exist.")
    sys.exit(1)  
  course_name = sys.argv[1].split('/')[-2]
  csv_name = course_name + ".csv"

  #make a list of students
  student_list = list()
  for student_name in os.listdir(user_dir):
    student_dir = os.path.join(user_dir, student_name)
    if not os.path.isdir(student_dir):
      continue
    else:
      print("Added " + student_name)
      student_list.append(student_name)

  f = open(csv_name, 'wt')
  try:
    with open(os.path.join(os.getcwd(), csv_name), "w") as out_file:
      writer = csv.writer(out_file)
      for student in student_list:
        #TODO: Do something clever with the name/last name/email/section.
        writer.writerow((student, "a", "b", "c@email.com", str(1)))
  finally:
    f.close()

  

if __name__ == '__main__':
  main()