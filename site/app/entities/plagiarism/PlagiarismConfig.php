<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use app\exceptions\ValidationException;
use app\exceptions\FileNotFoundException;
use app\libraries\DateUtils;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use app\libraries\plagiarism\PlagiarismUtils;
use DateTime;

/**
 * Class PlagiarismConfig
 * @package app\entities\plagiarism
 */
#[ORM\Entity]
#[ORM\Table(name: "lichen")]
class PlagiarismConfig {
    /* VARIABLES */

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $gradeable_id;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $config_id;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $has_provided_code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $version;

    #[ORM\Column(type: "simple_array")]
    protected array $regex;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $regex_dir_submissions;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $regex_dir_results;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $regex_dir_checkout;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $language;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $threshold;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $hash_size;

    #[ORM\Column(type: Types::JSON)]
    protected array $other_gradeables;

    #[ORM\Column(type: "simple_array")]
    protected array $other_gradeable_paths;

    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    protected array $ignore_submissions;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected DateTime $last_run_timestamp;

    /**
     * @var Collection<PlagiarismRunAccess>
     */
    #[ORM\OneToMany(mappedBy: "lichen_run", targetEntity: PlagiarismRunAccess::class)]
    #[ORM\OrderBy(["timestamp" => "DESC"])]
    protected Collection $access_times;

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
        int $hash_size,
        array $other_gradeables,
        array $other_gradeable_paths,
        int $user_group,
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
        $this->setHashSize($hash_size);
        $this->setOtherGradeables($other_gradeables);
        $this->setOtherGradeablePaths($other_gradeable_paths, $user_group);
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

    public function hasProvidedCode(): bool {
        return $this->has_provided_code;
    }

    public function setHasProvidedCode(bool $provided_code_status): void {
        $this->has_provided_code = $provided_code_status;
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

    public function getHashSize(): int {
        return $this->hash_size;
    }

    /**
     * @throws ValidationException
     */
    public function setHashSize(int $hash_size): void {
        if ($hash_size > 1) {
            $this->hash_size = $hash_size;
        }
        else {
            throw new ValidationException("Error: Invalid hash size", []);
        }
    }

    public function getOtherGradeables(): array {
        return $this->other_gradeables;
    }

    public function setOtherGradeables(array $other_gradeables): void {
        $this->other_gradeables = $other_gradeables;
    }

    public function hasOtherGradeablePaths(): bool {
        return count($this->other_gradeable_paths) > 0;
    }

    public function getOtherGradeablePaths(): array {
        return $this->other_gradeable_paths;
    }

    /**
     * This function is passed an array of paths.  We assume that any string splitting has already occurred prior to
     * passing the array to this function.  This function also performs some basic error checking for each path to
     * ensure that each path has the same group as the current signed in user.
     */
    public function setOtherGradeablePaths(array $paths, int $user_group): void {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                throw new FileNotFoundException("Error: Unable to read file {$path}");
            }
            if (filegroup($path) !== $user_group) {
                throw new ValidationException("Error: Path {$path} does not share group '{$user_group}' with current user", []);
            }
        }
        $this->other_gradeable_paths = $paths;
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
        $this->last_run_timestamp = DateUtils::getDateTimeNow();
    }

    public function userHasAccessed(string $user_id): bool {
        return $this->access_times->filter(function (PlagiarismRunAccess $access) use ($user_id) {
            return $access->getUser()->getId() === $user_id;
        })->count() > 0;
    }

    public function addAccess(PlagiarismRunAccess $timestamp): void {
        $this->access_times[] = $timestamp;
    }
}
