<?php

declare(strict_types=1);

namespace app\data_objects;

use app\libraries\DateUtils;
use app\exceptions\DockerLogParseException;
use app\libraries\Utils;

/**
 * Simple class to represent docker image from parsed information
 */
class DockerImage {
    /** The Docker image ID */
    public string $id;
    /** Date of creation, reported by Docker */
    public \DateTime $created;
    /** Human readable timestamp from the date of creation */
    public string $created_timestamp;
    /** size of the image in human readable format */
    public string $size_mb;
    /** The name:tag of the image */
    public string $primary_name;
    /** @var array<string> $aliases - Any other name:tags that also point to this image */
    public array $aliases = [];
    /** @var array<string> What Submitty capabilities this image is associated with */
    public array $capabilities = [];
    /** Create a new DockerImage object*/
    public function __construct(string $id, \DateTime $created, string $size, string $digest) {
        $this->id = $id;
        $this->created = $created;
        $this->created_timestamp = DateUtils::dateTimeToString($created);
        $this->size_mb = $size;
        $this->digest = $digest;
    }

    /**
     * Construct a new DockerImage from log lines
     * @param array<string> $logLines
     * @throws DockerLogParseException
     */
    public static function fromLog(array $logLines): self {
        if (count($logLines) < 4) {
            throw new DockerLogParseException("Unexpected log input, insufficient lines for image details.");
        }

        // Parse ID
        if (preg_match("/\t-id: (.+)/", $logLines[0], $matches) === 0) {
            throw new DockerLogParseException("Unexpected log input, attempted to read image ID.");
        }

        $id = $matches[1];
        // Parse created date
        if (preg_match("/\t-created: (.+)/", $logLines[1], $matches) === 0) {
            throw new DockerLogParseException("Unexpected log input, attempted to read image creation date.");
        }

        $created = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $matches[1]);
        if (!$created) {
            throw new DockerLogParseException("Invalid date format in log input.");
        }

        // Parse size
        if (preg_match("/\t-size: (.+)/", $logLines[2], $matches) === 0) {
            throw new DockerLogParseException("Unexpected log input, attempted to read image size.");
        }

        $bytes = (int) $matches[1];
        $size = Utils::formatBytes('mb', $bytes, true);
        
        // Parse digest
        if (preg_match("/\t-digest: (.+)/", $logLines[3], $matches) === 0) {
            throw new DockerLogParseException("Unexpected log input, attempted to read digest.");
        }

        $digest = $matches[1];
        return new self($id, $created, $size, $digest);
    }
}
