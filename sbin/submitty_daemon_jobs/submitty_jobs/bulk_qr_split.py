#!/usr/bin/env python3

"""Split PDFS by QR code and move images and PDFs to correct folder."""

import os
import traceback
import numpy
from . import write_to_log as logger
from . import submitty_ocr as scanner

# try importing required modules
try:
    from PyPDF2 import PdfFileReader, PdfFileWriter
    from pdf2image import convert_from_bytes
    import pyzbar.pyzbar as pyzbar
    from pyzbar.pyzbar import ZBarSymbol
    import cv2
except ImportError:
    traceback.print_exc()
    raise ImportError("One or more required python modules not installed correctly")


def main(args):
    """Scan through PDF and split PDF and images."""
    filename = args[0]
    split_path = args[1]
    qr_prefix = args[2]
    qr_suffix = args[3]
    log_file_path = args[4]
    use_ocr = args[5]

    buff = "Process " + str(os.getpid()) + ": "

    try:
        os.chdir(split_path)
        pdfPages = PdfFileReader(filename)
        pdf_writer = PdfFileWriter()
        i = id_index = 0
        page_count = 1
        prev_file = data = "BLANK"
        output = {"filename": filename, "is_qr": True, "use_ocr": use_ocr}
        json_file = os.path.join(split_path, "decoded.json")

        for page_number in range(pdfPages.numPages):
            # convert pdf to series of images for scanning
            page = convert_from_bytes(
                open(filename, 'rb').read(),
                first_page=page_number+1, last_page=page_number+2)[0]

            # increase contrast of image for better QR decoding
            cv_img = numpy.array(page)

            img_grey = cv2.cvtColor(cv_img, cv2.COLOR_BGR2GRAY)
            ret2, thresh = cv2.threshold(img_grey, 0, 255,
                                         cv2.THRESH_BINARY+cv2.THRESH_OTSU)

            # decode img - only look for QR codes
            val = pyzbar.decode(thresh, symbols=[ZBarSymbol.QRCODE])

            if val != []:
                # found a new qr code, split here
                # convert byte literal to string
                data = val[0][0].decode("utf-8")

                if not use_ocr:
                    buff += "Found a QR code with value \'" + data + "\' on"
                    buff += " page " + str(page_number) + ", "

                if data == "none":  # blank exam with 'none' qr code
                    data = "BLANK EXAM"
                else:
                    pre = data[0:len(qr_prefix)]
                    suf = data[(len(data)-len(qr_suffix)):len(data)]

                    if qr_prefix != '' and pre == qr_prefix:
                        data = data[len(qr_prefix):]
                    if qr_suffix != '' and suf == qr_suffix:
                        data = data[:-len(qr_suffix)]

                # since QR splitting doesn't know the max page assume length of 3
                prepended_index = str(i).zfill(3)

                cover_filename = '{}_{}_cover.pdf'.format(filename[:-4],
                                                          prepended_index)
                output_filename = '{}_{}.pdf'.format(filename[:-4], prepended_index)
                output[output_filename] = {}

                # if we're looking for a student's ID, use that as the value instead
                if use_ocr:
                    data, confidences = scanner.getDigits(thresh, val)
                    buff += "Found student ID number of \'" + data + "\' on"
                    buff += " page " + str(page_number) + ", "
                    buff += "Confidences: " + str(confidences) + " "
                    output[output_filename]["confidences"] = str(confidences)

                output[output_filename]['id'] = data
                # save pdf
                if i != 0 and prev_file != '':
                    output[prev_file]['page_count'] = page_count
                    # update json file
                    logger.write_to_json(json_file, output)
                    with open(prev_file, 'wb') as out:
                        pdf_writer.write(out)

                if id_index == 1:
                    # correct first pdf's page count and print file
                    output[prev_file]['page_count'] = page_count
                    with open(prev_file, 'wb') as out:
                        pdf_writer.write(out)

                # start a new pdf and grab the cover
                cover_writer = PdfFileWriter()
                pdf_writer = PdfFileWriter()
                cover_writer.addPage(pdfPages.getPage(i))
                pdf_writer.addPage(pdfPages.getPage(i))

                # save cover
                with open(cover_filename, 'wb') as out:
                    cover_writer.write(out)

                # save cover image
                page.save('{}.jpg'.format(cover_filename[:-4]), "JPEG", quality=100)

                id_index += 1
                page_count = 1
                prev_file = output_filename

                # save page as image, start indexing at 1
                page.save(prev_file[:-4] + '_' + str(page_count).zfill(3) + '.jpg',
                          "JPEG", quality=100)

            else:
                # the first pdf page doesn't have a qr code
                if i == 0:
                    prepended_index = str(i).zfill(3)
                    output_filename = '{}_{}.pdf'.format(filename[:-4], prepended_index)
                    cover_filename = '{}_{}_cover.pdf'.format(filename[:-4],
                                                              prepended_index)
                    output[output_filename] = {}
                    # set the value as blank so a human can check what happened
                    output[output_filename]['id'] = "BLANK"
                    prev_file = output_filename
                    id_index += 1
                    cover_writer = PdfFileWriter()
                    # save cover
                    cover_writer.addPage(pdfPages.getPage(i))
                    with open(cover_filename, 'wb') as out:
                        cover_writer.write(out)

                    # save cover image
                    page.save('{}.jpg'.format(cover_filename[:-4]), "JPEG", quality=100)

                # add pages to current split_pdf
                page_count += 1
                pdf_writer.addPage(pdfPages.getPage(i))
                # save page as image, start indexing at 1
                page.save(prev_file[:-4] + '_' + str(page_count).zfill(3) + '.jpg',
                          "JPEG", quality=100)

            i += 1

        buff += "Finished splitting into {} files\n".format(id_index)

        # save whatever is left
        prepended_index = str(i).zfill(3)
        output_filename = '{}_{}.pdf'.format(filename[:-4], prepended_index)
        output[prev_file]['id'] = data
        output[prev_file]['page_count'] = page_count
        if use_ocr:
            output[prev_file]['confidences'] = str(confidences)

        logger.write_to_json(json_file, output)

        with open(prev_file, 'wb') as out:
            pdf_writer.write(out)
        # write the buffer to the log file, so everything is on one line
        logger.write_to_log(log_file_path, buff)
    except Exception:
        msg = "Failed when splitting pdf " + filename
        print(msg)
        traceback.print_exc()
        # print everything in the buffer just in case it didn't write
        logger.write_to_log(log_file_path, buff)
        logger.write_to_log(log_file_path, msg + "\n" + traceback.format_exc())


if __name__ == "__main__":
    main()
