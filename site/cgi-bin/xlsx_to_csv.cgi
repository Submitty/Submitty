#!/usr/bin/env python3

"""
 This script will convert a given xlsx (excel) file to csv format using
 the xlsx2csv python package. The parameters to this script are a name
 for a XLSX file to convert and a name for the resulting CSV file. Deletion of
 these files should then be handled (if necessary) in the calling script, not
 in this file. Files handled by this script generally contain data that is
 regulate by FERPA (20 U.S.C. § 1232g) and thus should be treated in a manner
 such that unintended access is generally not possible. As such, the URL should
 not be indicated to the user (ie. through obvious redirection to this script)
 or by directly encoding it into a javascript ajax call. It should just be called
 via an internal call of the server, thus not making the url accessible.
"""

import cgi
import json
import os
import xlsx2csv

def print_error(message):
    print(json.dumps({"success": False, "error": True, "error_message": message}))

def main():
    print("Content-type: text/html")
    print()

    args = cgi.FieldStorage()
    xlsx_file = "/tmp/" + os.path.basename(args['xlsx_file'].value)
    csv_file = "/tmp/" + os.path.basename(args['csv_file'].value)

    if (not os.path.isfile(xlsx_file)):
        print_error("XLSX spreadsheet not found")
        return
    elif (not os.path.isfile(csv_file)):
        print_error("CSV file not found")
        return
    elif (not os.access(csv_file, os.W_OK)):
        print_error("Cannot write to CSV file")
        return

    # XLSX to CSV conversion
    xlsx2csv.Xlsx2csv(xlsx_file, outputencoding='utf-8', skip_empty_lines=True).convert(csv_file)

    # Validate result after conversion
    with open(csv_file, "r") as read_file:
        tmp = read_file.read()
        if (not tmp):
            print_error("Failed converting xlsx to csv.")
            return

    print(json.dumps({"success": True, "error": False}))

if __name__ == "__main__":
    main()
