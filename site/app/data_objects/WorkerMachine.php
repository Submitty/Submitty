<?php

declare(strict_types=1);

namespace app\data_objects;

/**
 * Simple class to represent a worker machine
 */
class WorkerMachine {
    /** The name of this worker */
    public string $name;
    /** number of workers this machine has */
    public int $num_autograding_workers;
    /** @var array<string> - What capabilities this worker has */
    public array $capabilities;
    /** Is this woker enabled or not */
    public bool $is_enabled;
    /** Set after parsing logs if this machine could not be updated */
    public bool $failed_to_update = false;
    /** Docker version on the machine */
    public string $docker_version = "Unknown";
    /** OS of the machine */
    public string $os = "Unknown";
    /** System information parsed from sysinfo logs */
    public ?WorkerMachineSystemInformation $system_information = null;

    /**
     * Create a new Worker object
     * @param array<string> $capabilities a list of capabilities this worker has
     */
    public function __construct(string $name, int $num_autograding_workers, array $capabilities, bool $is_enabled) {
        $this->name = $name;
        $this->num_autograding_workers = $num_autograding_workers;
        $this->capabilities = $capabilities;
        $this->is_enabled = $is_enabled;
    }
}
