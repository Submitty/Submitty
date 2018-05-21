"""
init file.
"""
from abc import ABC, abstractmethod

DATA_DIR = ''


class AbstractJob(ABC):
    def __init__(self, job_details):
        self.job_details = job_details

    @abstractmethod
    def run_job(self):
        pass
