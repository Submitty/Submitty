<?php

declare(strict_types=1);

namespace app\entities\poll;

use app\repositories\poll\PollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use DateTime;
use app\libraries\DateUtils;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollRepository::class)]
#[ORM\Table(name: "polls")]
class Poll {
    #[ORM\Id]
    #[ORM\Column(name: "poll_id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected $id;

    #[ORM\Column(name: "name", type: Types::TEXT)]
    protected $name;

    #[ORM\Column(type: Types::TEXT)]
    protected $question;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $duration;

    #[ORM\Column(type: Types::TEXT)]
    protected $status;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected DateTime $end_date;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    protected DateTime $release_date;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected $image_path;

    #[ORM\Column(type: Types::STRING)]
    protected $question_type;

    #[ORM\Column(type: Types::STRING)]
    protected $release_histogram;

    #[ORM\Column(type: Types::STRING)]
    protected $release_answer;

    /**
     * @var Collection<Option>
     */
    #[ORM\OneToMany(mappedBy: "poll", targetEntity: Option::class, orphanRemoval: true)]
    #[ORM\JoinColumn(name: "poll_id", referencedColumnName: "poll_id")]
    #[ORM\OrderBy(["order_id" => "ASC"])]
    protected Collection $options;

    /**
     * @var Collection<Response>
     */
    #[ORM\OneToMany(mappedBy: "poll", targetEntity: Response::class)]
    #[ORM\JoinColumn(name: "poll_id", referencedColumnName: "poll_id")]
    protected Collection $responses;

    public function __construct(string $name, string $question, string $question_type, \DateInterval $duration, \DateTime $release_date, string $release_histogram, string $release_answer, string $image_path = null) {
        $this->setName($name);
        $this->setQuestion($question);
        $this->setQuestionType($question_type);
        $this->setDuration($duration);
        $this->setClosed();
        $this->setReleaseDate($release_date);
        $this->setReleaseHistogram($release_histogram);
        $this->setReleaseAnswer($release_answer);
        $this->setImagePath($image_path);
        $this->options = new ArrayCollection();
        $this->responses = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getQuestion(): string {
        return $this->question;
    }

    public function setQuestion(string $question): void {
        $this->question = $question;
    }
    public function setClosed(): void {
        $this->end_date = new \DateTime(DateUtils::BEGINING_OF_TIME);
    }
    public function setOpen(): void {
        $this->end_date = new \DateTime(DateUtils::MAX_TIME);
    }
    public function setEnded(): void {
        $temp = DateUtils::getDateTimeNow();
        $tempString = $temp->format('Y-m-d');
        $this->end_date = new DateTime($tempString);
    }
    public function isOpen(): bool {
        $now = DateUtils::getDateTimeNow();
        return $now < $this->end_date;
    }

    public function isEnded(): bool {
        $now = DateUtils::getDateTimeNow();
        $closeDate = DateUtils::BEGINING_OF_TIME;
        return $now > $this->end_date && $this->end_date->format('Y-m-d\TH:i:s') !== $closeDate;
    }

    public function isClosed(): bool {
        $now = DateUtils::getDateTimeNow();
        return $now > $this->end_date && $this->end_date->format('Y-m-d\TH:i:s') === DateUtils::BEGINING_OF_TIME;
    }

    public function getDuration(): \DateInterval {
        $seconds = $this->duration;
        $years = floor($seconds / (365 * 24 * 60 * 60));
        $seconds -= $years * (365 * 24 * 60 * 60);

        $months = floor($seconds / (30 * 24 * 60 * 60));
        $seconds -= $months * (30 * 24 * 60 * 60);

        $days = floor($seconds / (24 * 60 * 60));
        $seconds -= $days * (24 * 60 * 60);

        $hours = floor($seconds / (60 * 60));
        $seconds -= $hours * (60 * 60);

        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;
        return new \DateInterval("P{$years}Y{$months}M{$days}DT{$hours}H{$minutes}M{$seconds}S");
    }

    public function getEndDate(): \DateTime {
        return $this->end_date;
    }

    public function getReleaseDate(): \DateTime {
        return $this->release_date;
    }

    public function setDuration(\DateInterval $duration): void {
        $totalSeconds = $duration->s;
        $totalSeconds += $duration->i * 60;
        $totalSeconds += $duration->h * 3600;
        $totalSeconds += $duration->d * 86400;
        $totalSeconds += $duration->m * 2592000;
        $totalSeconds += $duration->y * 31536000;
        $this->duration = $totalSeconds;
    }

    public function setEndDate(\DateTime $end_date): void {
        $this->end_date = $end_date;
    }

    public function setReleaseDate(\DateTime $release_date): void {
        $this->release_date = $release_date;
    }

    public function getImagePath(): ?string {
        return $this->image_path;
    }

    public function setImagePath(?string $image_path): void {
        $this->image_path = $image_path;
    }

    public function getQuestionType(): string {
        return $this->question_type;
    }

    public function setQuestionType(string $question_type): void {
        $this->question_type = $question_type;
    }

    public function setReleaseHistogram(string $status): void {
        if ($status !== "never" && $status !== "always" && $status !== "when_ended") {
            throw new \RuntimeException("Invalid release histogram status");
        }
        $this->release_histogram = $status;
    }

    /**
     * Note: This function should only be used if the actual string is desired.  (exporting poll data for example)
     *       isHistogramAvailable() is preferred if at all possible.
     */
    public function getReleaseHistogram(): string {
        return $this->release_histogram;
    }

    public function isHistogramAvailable(): bool {
        return ($this->release_histogram === "always" && !$this->isClosed()) || ($this->release_histogram === "when_ended" && $this->isEnded());
    }

    /**
     * Note: This function should only be used if the actual string is desired.  (exporting poll data for example)
     *       isReleaseAnswer() is preferred if at all possible.
     */

    public function setReleaseAnswer(string $status): void {
        if ($status !== "never" && $status !== "always" && $status !== "when_ended") {
            throw new \RuntimeException("Invalid release answer status");
        }
        $this->release_answer = $status;
    }

    public function getReleaseAnswer(): string {
        return $this->release_answer;
    }

    public function isReleaseAnswer(): bool {
        return ($this->release_answer === "always" && !$this->isClosed()) || ($this->release_answer === "when_ended" && $this->isEnded());
    }

    /**
     * @return Collection<Option>
     */
    public function getOptions(): Collection {
        return $this->options;
    }

    public function getOptionById(int $option_id): Option {
        foreach ($this->options as $option) {
            if ($option->getId() === $option_id) {
                return $option;
            }
        }
        throw new \RuntimeException("Invalid option id");
    }

    public function addOption(Option $option): void {
        $this->options->add($option);
        $option->setPoll($this);
    }

    public function removeOption(Option $option): void {
        $this->options->removeElement($option);
    }

    public function addResponse(Response $response, int $option_id) {
        $this->responses->add($response);
        $response->setOption($this->getOptionById($option_id));
        $response->setPoll($this);
    }

    /**
     * @return Collection<Response>
     */
    public function getUserResponses(): Collection {
        return $this->responses;
    }

    /**
     * Return an array of options from the set of responses loaded into this model
     */
    public function getSelectedOptionIds(): array {
        $selected_options = [];
        foreach ($this->getUserResponses() as $r) {
            $selected_options[] = $r->getOption()->getId();
        }
        return $selected_options;
    }
}
