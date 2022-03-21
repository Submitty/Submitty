#!/usr/bin/env python3

from .base_testcase import BaseTestCase
from submitty_jobs import bulk_upload_split
from pathlib import Path 
import os
import json


class TestBulkPdfSplit(BaseTestCase):
    def test_split_pdf(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '01_qr_six_6page.pdf'
        tgt_num_pages = 6

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            tgt_num_pages,
            str(split_path_dir.joinpath('bulk_upload_log.txt'))
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


    def test_bad_split_number(self):
        split_path_dir = self.working_dir.joinpath('split')
        tgt_filename = '01_qr_six_6page.pdf'
        tgt_num_pages = 40

        self.copyFiles([tgt_filename])

        args = [
            tgt_filename,
            str(split_path_dir),
            tgt_num_pages,
            str(split_path_dir.joinpath('bulk_upload_log.txt'))
        ]

        os.chdir(split_path_dir)
        bulk_upload_split.main(args)
        self.assertTrue(Path('bulk_upload_log.txt').is_file())

        with open('bulk_upload_log.txt', 'r') as file:
            self.assertTrue('01_qr_six_6page.pdf not divisible by 40' in file.read())

