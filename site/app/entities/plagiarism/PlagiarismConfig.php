<?php

declare(strict_types=1);

namespace app\entities\plagiarism;

use Exception;
use Doctrine\ORM\Mapping as ORM;
use app\libraries\plagiarism\PlagiarismUtils;

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

    /* FUNCTIONS */

    /**
     * PlagiarismConfig constructor.
     * @param string $gradeable_id
     * @param int $config_id
     * @param string $version
     * @param array $regex
     * @param bool $regex_dir_submissions
     * @param bool $regex_dir_results
     * @param bool $regex_dir_checkout
     * @param string $language
     * @param int $threshold
     * @param int $sequence_length
     * @param array $other_gradeables
     * @param array $ignored_submissions
     * @throws Exception
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
    }

    /**
     * @return int
     */
    public function getUniqueID(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getGradeableID(): string {
        return $this->gradeable_id;
    }

    /**
     * @return int
     */
    public function getConfigID(): int {
        return $this->config_id;
    }

    /**
     * @return string
     */
    public function getVersionStatus(): string {
        return $this->version;
    }

    /**
     * @param string $version_code
     * @throws Exception
     */
    public function setVersionStatus(string $version_code): void {
        if ($version_code === "active_version" || $version_code === "all_versions") {
            $this->version = $version_code;
        }
        else {
            throw new Exception("Error: Version must either be 'active_version' or 'all_versions', $version_code provided.");
        }
    }

    /**
     * @return array
     */
    public function getRegexArray(): array {
        return $this->regex;
    }

    /**
     * @param array $regex
     * @throws Exception
     */
    public function setRegexArray(array $regex): void {
        foreach ($regex as $r) {
            if (str_contains($r, "..")) {
                throw new Exception("Error: Regex contains invalid component '..'");
            }
        }
        $this->regex = $regex;
    }

    /**
     * @return bool
     */
    public function isRegexDirSubmissionsSelected(): bool {
        return $this->regex_dir_submissions;
    }

    /**
     * @param bool $is_selected
     */
    public function setRegexDirSubmissions(bool $is_selected): void {
        $this->regex_dir_submissions = $is_selected;
    }

    /**
     * @return bool
     */
    public function isRegexDirResultsSelected(): bool {
        return $this->regex_dir_results;
    }

    /**
     * @param bool $is_selected
     */
    public function setRegexDirResults(bool $is_selected): void {
        $this->regex_dir_results = $is_selected;
    }

    /**
     * @return bool
     */
    public function isRegexDirCheckoutSelected(): bool {
        return $this->regex_dir_checkout;
    }

    /**
     * @param bool $is_selected
     */
    public function setRegexDirCheckout(bool $is_selected): void {
        $this->regex_dir_checkout = $is_selected;
    }

    /**
     * @return string
     */
    public function getLanguage(): string {
        return $this->language;
    }

    /**
     * @param string $language
     * @throws Exception
     */
    public function setLanguage(string $language): void {
        if (in_array($language, PlagiarismUtils::getSupportedLanguages())) {
            $this->language = $language;
        }
        else {
            throw new Exception("Error: Provided language not in list of allowed languages");
        }
    }

    /**
     * @return int
     */
    public function getThreshold(): int {
        return $this->threshold;
    }

    /**
     * @param int $threshold
     * @throws Exception
     */
    public function setThreshold(int $threshold): void {
        if ($threshold > 1) {
            $this->threshold = $threshold;
        }
        else {
            throw new Exception("Error: Invalid threshold value");
        }
    }

    /**
     * @return int
     */
    public function getSequenceLength(): int {
        return $this->sequence_length;
    }

    /**
     * @param int $sequence_length
     * @throws Exception
     */
    public function setSequenceLength(int $sequence_length): void {
        if ($sequence_length > 1) {
            $this->sequence_length = $sequence_length;
        }
        else {
            throw new Exception("Error: Invalid sequence length");
        }
    }

    /**
     * @return array
     */
    public function getOtherGradeables(): array {
        return $this->other_gradeables;
    }

    /**
     * @param array $other_gradeables
     */
    public function setOtherGradeables(array $other_gradeables): void {
        $this->other_gradeables = $other_gradeables;
    }

    /**
     * @return array
     */
    public function getIgnoredSubmissions(): array {
        return $this->ignore_submissions;
    }

    /**
     * @param array $ignored_submissions
     */
    public function setIgnoredSubmissions(array $ignored_submissions): void {
        $this->ignore_submissions = $ignored_submissions;
    }
}
