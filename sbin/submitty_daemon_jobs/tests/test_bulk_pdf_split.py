#!/usr/bin/env python3

from .base_testcase import BaseTestCase
from submitty_jobs import bulk_upload_split, bulk_qr_split
from pathlib import Path 
import os
import json
import urllib.parse

class TestBulkPdfSplit(BaseTestCase):
    #Test splitting a PDF by a given number of pages
    def test_split_pdf(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '01_qr_six_6page.pdf'
        tgt_num_pages = 6

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            tgt_num_pages,
            str(split_path_dir.joinpath('bulk_upload_log.txt')),
        ]

        os.chdir(split_path_dir)
        bulk_upload_split.main(args)

        self.assertTrue(Path('decoded.json').is_file())
        with open('decoded.json', 'r') as bulk_json:
            metadata = json.load(bulk_json)

            self.assertEqual(metadata['filename'], tgt_filename)
            self.assertEqual(metadata['is_qr'], False)
            self.assertEqual(metadata['page_count'], tgt_num_pages)


        file_name = Path(tgt_filename).stem

        #01_qr_six_6page.pdf contains 36 pages, expected to get 6 split pdfs with 6 pages each
        #also expect a png copy of each page and a cover image 
        for i_idx in range(0,tgt_num_pages*tgt_num_pages,tgt_num_pages):
            split_tgt = Path(file_name + '_' + str(i_idx).zfill(2) + '.pdf')
            self.assertTrue(split_tgt.is_file())

            cover_tgt = Path(file_name + '_' + str(i_idx).zfill(2) + '_cover.pdf')
            self.assertTrue(split_tgt.is_file())

            #verify each page png is being produced
            for j_idx in range(1,tgt_num_pages+1):
                page_tgt = Path(file_name + '_' + str(i_idx).zfill(2) + '_' + str(j_idx).zfill(3)  + '.jpg')
                self.assertTrue(page_tgt.is_file())


    #Test handling a bad number of given pages to split a pdf gracefully
    def test_bad_split_number(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '01_qr_six_6page.pdf'
        tgt_num_pages = 40

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            tgt_num_pages,
            str(split_path_dir.joinpath('bulk_upload_log.txt')),
        ]

        os.chdir(split_path_dir)
        bulk_upload_split.main(args)
        self.assertTrue(Path('bulk_upload_log.txt').is_file())

        with open('bulk_upload_log.txt', 'r') as file:
            self.assertTrue('01_qr_six_6page.pdf not divisible by 40' in file.read())


    #Test splitting a PDF by scanning for a QR code on each page and splitting when one is detected
    #Test verifying the QR code's contents in the decoded.json is as expected
    def test_split_qr(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '01_qr_six_6page.pdf'
        tgt_num_pages = 6

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            "",
            "",
            str(split_path_dir.joinpath('bulk_upload_log.txt')),
            False
        ]

        os.chdir(split_path_dir)
        bulk_qr_split.main(args)

        #01_qr_six_6page.pdf contains 36 pages, every 6 pages contains a qr code
        #also expect a png copy of each page and a cover image 
        self.assertTrue(Path('decoded.json').is_file())
        file_name = Path(tgt_filename).stem

        with open('decoded.json', 'r') as bulk_json:
            metadata = json.load(bulk_json)
            for i_idx in range(0,tgt_num_pages*tgt_num_pages,tgt_num_pages):
                split_filename = file_name + '_' + str(i_idx).zfill(3) + '.pdf'

                self.assertTrue(split_filename in metadata)
                self.assertEqual(metadata[split_filename]['page_count'], tgt_num_pages)

                split_tgt = Path(split_filename)
                self.assertTrue(split_tgt.is_file())

                cover_tgt = Path(file_name + '_' + str(i_idx).zfill(3) + '_cover.pdf')
                self.assertTrue(split_tgt.is_file())

                #verify each page png is being produced
                for j_idx in range(1,tgt_num_pages+1):
                    page_tgt = Path(file_name + '_' + str(i_idx).zfill(3) + '_' + str(j_idx).zfill(3)  + '.jpg')
                    self.assertTrue(page_tgt.is_file())



    def test_split_qr_url(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '03_qr_stress_test_colorscan.pdf'
        tgt_num_pages = 6

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            "https://url_testing.com/tests/?code=koalas&name=",
            urllib.parse.quote("#D0,.com"),
            str(split_path_dir.joinpath('bulk_upload_log.txt')),
            False,
        ]


        os.chdir(split_path_dir)
        bulk_qr_split.main(args)

        #03_qr_stress_test_colorscan.pdf contains 36 pages, every 6 pages contains a qr code
        #also expect a png copy of each page and a cover image 
        self.assertTrue(Path('decoded.json').is_file())
        file_name = Path(tgt_filename).stem

        with open('decoded.json', 'r') as bulk_json:
            metadata = json.load(bulk_json)
            self.assertTrue('use_ocr' in metadata)
            self.assertEqual(metadata['use_ocr'], False)

            for i_idx in [0,5,7,11,15,23,31]:
                split_filename = file_name + '_' + str(i_idx).zfill(3) + '.pdf'

                self.assertTrue(split_filename in metadata)

                #03_qr_stress_test_colorscan.pdf does not start with a QR code, so index = 0's id will be 'BLANK'
                if i_idx > 0:
                    #the qr codes encode a URL in 03_qr_stress_test_colorscan.pdf, make sure the prefix and suffix given remove everything except the user id
                    user_id = metadata[split_filename]['id']
                    self.assertTrue("https://url_testing.com/tests/?code=koalas&name=" not in user_id)
                    self.assertTrue(urllib.parse.quote("#D0,.com") not in user_id)
                    self.assertTrue(len(user_id) < 10)
                else:
                    self.assertEqual(metadata[split_filename]['id'], 'BLANK')


    def test_split_qr_ocr(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '04_numeric_id_scan.pdf'
        tgt_num_pages = 6

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            "",
            "",
            str(split_path_dir.joinpath('bulk_upload_log.txt')),
            True,
        ]

        os.chdir(split_path_dir)
        bulk_qr_split.main(args)

        self.assertTrue(Path('decoded.json').is_file())

        file_name = Path(tgt_filename).stem
        with open('decoded.json', 'r') as bulk_json:
            metadata = json.load(bulk_json)
            self.assertTrue('is_qr' in metadata)
            self.assertTrue('use_ocr' in metadata)
            self.assertTrue(metadata['is_qr'], True)
            self.assertTrue(metadata['use_ocr'], True)

            for i_idx in range(31):
                split_filename = file_name + '_' + str(i_idx).zfill(3) + '.pdf'
                self.assertTrue(split_filename in metadata)

                scan = metadata[split_filename]

                self.assertTrue('confidences' in scan)
                self.assertTrue('id' in scan)
                #self.assertTrue('page_count' in scan)
                #self.assertEqual(scan['page_count'], 1)


