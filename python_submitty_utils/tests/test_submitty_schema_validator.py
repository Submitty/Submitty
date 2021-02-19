from io import StringIO
from pathlib import Path
import sys
from unittest import TestCase

from submitty_utils import submitty_schema_validator as validator

SCHEMA_PATH = Path(
    Path(__file__).resolve().parent.parent.parent,
    'bin',
    'json_schemas',
    'complete_config_schema.json'
)
CONFIGS_DIR = Path(__file__).resolve().parent / 'data' / 'complete_configs'


class TestSubmittySchemaValidator(TestCase):
    def setUp(self):
        sys.stdout = StringIO()
        sys.stderr = StringIO()

    def tearDown(self):
        sys.stdout = sys.__stdout__
        sys.stderr = sys.__stderr__

    def test_valid_schemas(self):
        for entry in CONFIGS_DIR.iterdir():
            with self.subTest(f'Validating {entry.name}'):
                validator.validate_complete_config_schema_using_filenames(
                    str(entry),
                    str(SCHEMA_PATH)
                )
                self.assertTrue(True)
