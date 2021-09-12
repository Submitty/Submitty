<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use app\exceptions\ValidationException;
use Exception;
use Doctrine\ORM\Mapping as ORM;
use app\libraries\plagiarism\PlagiarismUtils;
use DateTime;

/**
 * Class PlagiarismConfig
 * @package app\entities\plagiarism
 * @ORM\Entity
 * @ORM\Table(name="lichen")
 */
class PlagiarismConfig {
    /* VARIABLES */

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $gradeable_id;

    /**
     * @ORM\Column(type="smallint")
     * @var int
     */
    protected $config_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $version;

    /**
     * @ORM\Column(type="simple_array")
     * @var array
     */
    protected $regex;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $regex_dir_submissions;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $regex_dir_results;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $regex_dir_checkout;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    protected $language;

    /**
     * @ORM\Column(type="smallint")
     * @var int
     */
    protected $threshold;

    /**
     * @ORM\Column(type="smallint")
     * @var int
     */
    protected $sequence_length;

    /**
     * @ORM\Column(type="json")
     * @var array
     */
    protected $other_gradeables;

    /**
     * @ORM\Column(type="simple_array")
     * @var array
     */
    protected $ignore_submissions;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $last_run_timestamp;

    /* FUNCTIONS */

    /**
     * PlagiarismConfig constructor.
     * @throws ValidationException
     */
    public function __construct(
        string $gradeable_id,
        int $config_id,
        string $version,
        array $regex,
        bool $regex_dir_submissions,
        bool $regex_dir_results,
        bool $regex_dir_checkout,
        string $language,
        int $threshold,
        int $sequence_length,
        array $other_gradeables,
        array $ignored_submissions
    ) {
        $this->gradeable_id = $gradeable_id;
        $this->config_id = $config_id;
        $this->setVersionStatus($version);
        $this->setRegexArray($regex);
        $this->setRegexDirSubmissions($regex_dir_submissions);
        $this->setRegexDirResults($regex_dir_results);
        $this->setRegexDirCheckout($regex_dir_checkout);
        $this->setLanguage($language);
        $this->setThreshold($threshold);
        $this->setSequenceLength($sequence_length);
        $this->setOtherGradeables($other_gradeables);
        $this->setIgnoredSubmissions($ignored_submissions);
        $this->setLastRunToCurrentTime();
    }

    public function getUniqueID(): int {
        return $this->id;
    }

    public function getGradeableID(): string {
        return $this->gradeable_id;
    }

    public function getConfigID(): int {
        return $this->config_id;
    }

    public function getVersionStatus(): string {
        return $this->version;
    }

    /**
     * @throws ValidationException
     */
    public function setVersionStatus(string $version_code): void {
        if ($version_code === "active_version" || $version_code === "all_versions") {
            $this->version = $version_code;
        }
        else {
            throw new ValidationException("Error: Version must either be 'active_version' or 'all_versions', $version_code provided.", []);
        }
    }

    public function getRegexArray(): array {
        return $this->regex;
    }

    /**
     * @throws ValidationException
     */
    public function setRegexArray(array $regex): void {
        foreach ($regex as $r) {
            if (str_contains($r, "..")) {
                throw new ValidationException("Error: Regex contains invalid component '..'", []);
            }
        }
        $this->regex = $regex;
    }

    public function isRegexDirSubmissionsSelected(): bool {
        return $this->regex_dir_submissions;
    }

    public function setRegexDirSubmissions(bool $is_selected): void {
        $this->regex_dir_submissions = $is_selected;
    }

    public function isRegexDirResultsSelected(): bool {
        return $this->regex_dir_results;
    }

    public function setRegexDirResults(bool $is_selected): void {
        $this->regex_dir_results = $is_selected;
    }

    public function isRegexDirCheckoutSelected(): bool {
        return $this->regex_dir_checkout;
    }

    public function setRegexDirCheckout(bool $is_selected): void {
        $this->regex_dir_checkout = $is_selected;
    }

    public function getLanguage(): string {
        return $this->language;
    }

    /**
     * @throws ValidationException
     */
    public function setLanguage(string $language): void {
        if (in_array($language, array_keys(PlagiarismUtils::SUPPORTED_LANGUAGES))) {
            $this->language = $language;
        }
        else {
            throw new ValidationException("Error: Provided language not in list of allowed languages", []);
        }
    }

    public function getThreshold(): int {
        return $this->threshold;
    }

    /**
     * @throws ValidationException
     */
    public function setThreshold(int $threshold): void {
        if ($threshold > 1) {
            $this->threshold = $threshold;
        }
        else {
            throw new ValidationException("Error: Invalid threshold value", []);
        }
    }

    public function getSequenceLength(): int {
        return $this->sequence_length;
    }

    /**
     * @throws ValidationException
     */
    public function setSequenceLength(int $sequence_length): void {
        if ($sequence_length > 1) {
            $this->sequence_length = $sequence_length;
        }
        else {
            throw new ValidationException("Error: Invalid sequence length", []);
        }
    }

    public function getOtherGradeables(): array {
        return $this->other_gradeables;
    }

    public function setOtherGradeables(array $other_gradeables): void {
        $this->other_gradeables = $other_gradeables;
    }

    public function getIgnoredSubmissions(): array {
        return $this->ignore_submissions;
    }

    public function setIgnoredSubmissions(array $ignored_submissions): void {
        $this->ignore_submissions = $ignored_submissions;
    }

    public function getLastRunTimestamp(): DateTime {
        return $this->last_run_timestamp;
    }

    public function setLastRunToCurrentTime(): void {
        $this->last_run_timestamp = new DateTime();
    }
}
