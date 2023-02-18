<?php

declare(strict_types=1);

namespace app\entities\poll;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="app\repositories\poll\PollRepository")
 * @ORM\Table(name="polls")
 */
class Poll {
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="poll_id",type="integer")
     */
    private $id;

    /** @ORM\Column(name="name",type="text") */
    private $name;

    /** @ORM\Column(type="text") */
    private $question;

    /** @ORM\Column(type="text") */
    private $status;

    /**
     * @ORM\Column(type="date")
     * @var \DateTime
     */
    private $release_date;

    /** @ORM\Column(type="text",nullable=true) */
    private $image_path;

    /**
     * @ORM\Column(type="string")
     */
    private $question_type;

    /**
     * @ORM\Column(type="string")
     */
    private $release_histogram;

    /**
     * @ORM\Column(type="string")
     */
    private $release_answer;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\poll\Option",mappedBy="poll",orphanRemoval=true)
     * @ORM\JoinColumn(name="poll_id", referencedColumnName="poll_id")
     * @ORM\OrderBy({"order_id" = "ASC"})
     * @var Collection<Option>
     */
    private $options;

    /**
     * @ORM\OneToMany(targetEntity="\app\entities\poll\Response",mappedBy="poll")
     * @ORM\JoinColumn(name="poll_id", referencedColumnName="poll_id")
     * @var Collection<Response>
     */
    private $responses;

    public function __construct(string $name, string $question, string $question_type, \DateTime $release_date, string $release_histogram, string $release_answer, string $image_path = null) {
        $this->setName($name);
        $this->setQuestion($question);
        $this->setQuestionType($question_type);
        $this->setReleaseDate($release_date);
        $this->setReleaseHistogram($release_histogram);
        $this->setReleaseAnswer($release_answer);
        $this->setImagePath($image_path);
        $this->setClosed();

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

    public function getStatus(): string {
        return $this->status;
    }

    public function setOpen(): void {
        $this->status = "open";
    }

    public function isOpen(): bool {
        return $this->status === "open";
    }

    public function setClosed(): void {
        $this->status = "closed";
    }

    public function isClosed(): bool {
        return $this->status === "closed";
    }

    public function setEnded(): void {
        $this->status = "ended";
    }

    public function isEnded(): bool {
        return $this->status === "ended";
    }

    public function getReleaseDate(): \DateTime {
        return $this->release_date;
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
