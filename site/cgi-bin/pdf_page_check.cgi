#!/usr/bin/env python3

"""
This script reads a PDF file and checks if the number of pages is divisible by a specified number of pages per document.
"""

import cgi
import json
from PyPDF2 import PdfReader

def print_error(message):
    print(json.dumps({"success": False, "error": True, "error_message": message}))

def main():
    print("Content-type: text/html")
    print()

    with open("/usr/local/submitty/config/submitty.json") as data:
        config = json.load(data)

    args = cgi.FieldStorage()
    pdf_path = args['pdf_path'].value
    num_page = int(args['num_page'].value)
    file_name = args['file_name'].value

    input_pdf = PdfReader(open(pdf_path, "rb"), strict=False)

    if len(input_pdf.pages) % num_page != 0:
        message = f"{file_name} not divisible by {num_page}"
        print_error(message)
        return


    print(json.dumps({"success": True, "error": False}))



if __name__ == "__main__":
    main()
