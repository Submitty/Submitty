"""The shipper scheduler."""

import multiprocessing
import os

from abc import ABC, abstractmethod
from typing import List

from .config import Config


class Worker:
    """A representation of an autograder instance.

    Parameters
    ----------
    config : Config
        Submitty configuration object.
    name : str
        Name used to uniquely identify this worker.
    properties : dict
        A dictionary mapping property names to property values.
    shipper_process : multiprocessing.Process
        The shipper process that will ship jobs to this worker.
    """

    def __init__(
        self,
        config: Config,
        name: str,
        properties: dict,
        shipper_process: multiprocessing.Process,
    ):
        self.config = config
        self.name = name
        self.properties = properties
        self.process = shipper_process
        self.folder = os.path.join(
            self.config.submitty['submitty_data_dir'],
            'in_progress_grading',
            self.name,
        )

    def is_enabled(self) -> bool:
        """Check if this worker is enabled."""
        return self.properties.get('enabled', False)

    def is_shipper_process_alive(self) -> bool:
        """Check if the worker's corresponding shipper process is still alive."""
        return self.process.is_alive()

    def is_idle(self) -> bool:
        """Check whether this worker's individual job queue is empty."""
        return len(os.listdir(self.folder)) == 0


class BaseScheduler(ABC):
    """Base class for Submitty job schedulers.

    Subclasses should override the `_assign_jobs` method, which is the important piece of
    functionality behind each scheduler.

    Parameters
    ----------
    config : Config
        Submitty configuration object.
    workers: list of Worker
        The workers that this scheduler has to schedule jobs for.
    """

    def __init__(self, config: Config, workers: List[Worker]):
        self.config = config
        self.workers = workers

        self.queue_folder = os.path.join(
            self.config.submitty['submitty_data_dir'],
            'to_be_graded_queue'
        )

    def _list_jobs(self) -> list:
        """Get a list of paths to jobs to be scheduled.

        Note that these paths have no intrinsic order to them.
        """
        jobs = [
            os.path.join(self.queue_folder, file)
            for file in os.listdir(self.queue_folder)
            if os.path.isfile(os.path.join(self.queue_folder, file))
        ]
        return jobs

    def update_and_schedule(self):
        """Check for new jobs and schedule them onto workers, if applicable."""
        for worker in self.workers:
            if not worker.is_shipper_process_alive():
                self.config.logger.log_message(
                    f"WARNING: Worker process {worker.name} is not alive!"
                )
        self._assign_jobs(self._list_jobs)

    @abstractmethod
    def _assign_jobs(self, jobs: list):
        """Assign the jobs in `jobs` to workers."""
        raise NotImplementedError()
