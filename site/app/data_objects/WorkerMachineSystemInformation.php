<?php

declare(strict_types=1);

namespace app\data_objects;

/**
 * Store information about a worker system information, this comes from system log information
 * instead of configuration
 */
class WorkerMachineSystemInformation {
    /** Name of worker machine this information is associated with */
    public string $associated_worker;
    /** State of worker_service */
    public string $worker_service;
    /** State of worker_service */
    public ?string $shipper_service;
    /** State of worker_service */
    public ?string $daemon_service;
    /** Disk usage in percetange */
    public string $disk_usage;
    /** String with a unix load average
     * See https://www.gnu.org/software/coreutils/manual/html_node/uptime-invocation.html#uptime-invocation
     * For understanding the load numbers
     */
    public string $load;
    /** operating system of the worker */
    public string $os;

    /** Create a new WorkerSystemInformation object */
    public function __construct(
        string $associated_worker,
        string $worker_service,
        string $shipper_service,
        string $daemon_service,
        string $disk_usage,
        string $load
    ) {

        $this->associated_worker = $associated_worker;
        $this->worker_service = $worker_service;
        $this->shipper_service = $shipper_service;
        $this->daemon_service = $daemon_service;
        $this->disk_usage = $disk_usage;
        $this->load = $load;
    }

    /**
     * Given an associative array of worker system information and the worker name, convert it to a class and
     * perform null checks over the data
     * @param array<string, ?string> $data - array from parsed json data to turn into a \WorkerMachineSystemInformation object
     */
    public static function fromArray(array $data, string $name): self {
        return new self(
            $name,
            $data['worker'] ?? 'No status collected',
            $data['shipper'] ?? null,
            $data['daemon'] ?? null,
            $data['disk'] ?? 'No status collected',
            $data['load'] ?? 'No status collected'
        );
    }
}
