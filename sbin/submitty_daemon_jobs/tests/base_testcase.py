#!/usr/bin/env python3

import unittest
import tempfile
import pathlib
import shutil
import json
import os


class BaseTestCase(unittest.TestCase):

    def setUp(self):
        self.starting_dir = os.getcwd()
        self.tmp_dir = tempfile.TemporaryDirectory()
        self.working_dir = pathlib.Path(self.tmp_dir.name).absolute()
        self.test_pdf_dir = pathlib.Path('../../more_autograding_examples/bulk_upload_pdfs/submissions').resolve()

        self.working_dir.joinpath('split').mkdir(parents=True, exist_ok=True)
        
    def tearDown(self):
        self.tmp_dir.cleanup()
        os.chdir(self.starting_dir)


    #given a list of filenames, copy tgt files from the more_autograding directory to the tmp split directory
    def copyFiles(self, files : list):
        for file in files:
            shutil.copy(self.test_pdf_dir.joinpath(file), self.working_dir.joinpath('split'))


