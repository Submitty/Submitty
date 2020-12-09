import os
import shutil
import unittest

from unittest import mock

from autograder.autograding_utils import Logger


SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))

# Where to dump intermediate test files
WORKING_DIR = os.path.abspath(
    os.path.join('..', '..', '..', 'test_suite', 'unitTests', 'autograder')
)
# Temporary log directories
AUTOGRADING_LOGS = os.path.join(WORKING_DIR, 'autograding')
STACK_TRACES = os.path.join(WORKING_DIR, 'autograding_stack_traces')


def fake_log_filename(self) -> str:
    """Replaces the Logger's _log_filename function.

    This is necessary to avoid a situation where the test starts on one date and ends on another,
    which would cause a lot of grief during debugging. The filename generated through this
    function is fixed, predictable, and not time-based.
    """
    return "test_log.txt"


class TestLogger(unittest.TestCase):
    """Tests logger functionality."""

    @classmethod
    def tearDownClass(cls):
        """Tear down the mock environment."""
        shutil.rmtree(WORKING_DIR, ignore_errors=True)

    @classmethod
    def setUpClass(cls):
        """Set up a minimal environment for the logger."""
        shutil.rmtree(WORKING_DIR, ignore_errors=True)
        os.makedirs(WORKING_DIR, exist_ok=True)
        os.mkdir(AUTOGRADING_LOGS)
        os.mkdir(STACK_TRACES)

    @mock.patch('autograder.autogradingutils.Logger', '_log_filename', fake_log_filename)
    def _make_logger(self, capture_traces: bool = False) -> Logger:
        """Make a new logger object.

        This is a convenience function to return a logger with a patched _log_filename function.
        """
        return Logger(
            log_dir=AUTOGRADING_LOGS,
            stack_trace_dir=STACK_TRACES,
            capture_traces=capture_traces,
            job_id="TEST"
        )

    def test_simple_message(self):
        """Test that writing a simple log message writes the message to the right place."""
        logger = self._make_logger()
        logger.log_message("Hello world!")

        with open(logger.log_path) as f:
            lines = f.readlines()

        self.assertIn("Hello world!", lines[-1])

    def test_simple_stack_trace(self):
        """Test that writing a simple "stack trace" writes the data to the right place."""
        logger = self._make_logger()
        logger.log_stack_trace("Uh-oh!")

        with open(logger.stack_trace_path) as f:
            lines = f.readlines()

        self.assertTrue(any("Uh-oh!" in line for line in lines))

    def test_stack_trace_capture(self):
        """Test that stack trace capturing works as intended."""
        logger = self._make_logger(True)

        logger.log_stack_trace("Uh-oh!")
        logger.log_stack_trace("Batch uh-oh!", is_batch=True)
        logger.log_stack_trace("ID'd uh-oh!", job_id="UH-OH!")
        logger.log_stack_trace("Specified uh-oh!", which_untrusted="localhost")
        logger.log_stack_trace(
            "Full uh-oh!",
            is_batch=True, which_untrusted="localhost", job_id="UH-OH!"
        )

        # Accumulated traces should occur chronologically
        expected = [
            {
                'trace': "Uh-oh!",
                # The below are the default values for the function.
                # Note the 'NO JOB': this value isn't in the function signature (it is None), but
                # is replaced with the logger instance's job ID when omitted.
                'is_batch': False,
                'which_untrusted': "",
                'job_id': "NO JOB"
            },
            {
                'trace': "Batch uh-oh!",
                'is_batch': True,
                'which_untrusted': "",
                'job_id': "NO JOB"
            },
            {
                'trace': "ID'd uh-oh!",
                'is_batch': False,
                'which_untrusted': "",
                'job_id': "UH-OH!"
            },
            {
                'trace': "Specified uh-oh!",
                'is_batch': False,
                'which_untrusted': "localhost",
                'job_id': "NO JOB"
            },
            {
                'trace': "Full uh-oh!",
                'is_batch': True,
                'which_untrusted': "localhost",
                'job_id': "UH-OH!"
            },
        ]

        self.assertEqual(expected, logger.accumulated_traces)
