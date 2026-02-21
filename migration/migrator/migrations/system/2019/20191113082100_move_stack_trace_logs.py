"""Migration for the Submitty system."""
import os
import json
from pathlib import Path
import shutil

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    old_stack_trace_path = os.path.join(config.submitty['autograding_log_path'], 'stack_traces')
    new_stack_trace_path = os.path.join(config.submitty['site_log_path'], 'autograding_stack_traces')

    # If the old path exists and the new one does not, move old to new
    if os.path.exists(old_stack_trace_path) and not os.path.exists(new_stack_trace_path):
        shutil.move(old_stack_trace_path, new_stack_trace_path)
    # If neither the new nor old path exists, make the new stack trace directory
    elif not os.path.exists(new_stack_trace_path):
        os.mkdir(new_stack_trace_path)



def down(config):
    old_stack_trace_path = os.path.join(config.submitty['autograding_log_path'], 'stack_traces')
    new_stack_trace_path = os.path.join(config.submitty['site_log_path'], 'autograding_stack_traces')

    # If the new path exists and the old one does not, move new to old
    if os.path.exists(new_stack_trace_path) and not os.path.exists(old_stack_trace_path):
        shutil.move(new_stack_trace_path, old_stack_trace_path)
    # If neither the new nor old path exists, make the new stack trace directory
    elif not os.path.exists(old_stack_trace_path):
        os.mkdir(old_stack_trace_path)
