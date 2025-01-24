<?php

declare(strict_types=1);

namespace app\entities\poll;

use app\repositories\poll\OptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OptionRepository::class)]
#[ORM\Table(name: "poll_options")]
class Option {
    #[ORM\Id]
    #[ORM\Column(name: "option_id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(name: "author_id", type: Types::TEXT, nullable: true)]
    protected ?string $author_id;

    #[ORM\Column(name: "order_id", type: Types::INTEGER)]
    protected int $order_id;

    #[ORM\Column(name: "response", type: Types::TEXT)]
    protected string $response;

    #[ORM\Column(name: "correct", type: Types::BOOLEAN)]
    protected bool $correct;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: "options")]
    #[ORM\JoinColumn(name: "poll_id", referencedColumnName: "poll_id", nullable: false)]
    protected Poll $poll;

    /**
     * @var Collection<Response>
     */
    #[ORM\OneToMany(mappedBy: "option", targetEntity: Response::class)]
    #[ORM\JoinColumn(name: "option_id", referencedColumnName: "option_id")]
    protected Collection $user_responses;

    public function __construct(int $order_id, string $response, bool $is_correct, ?string $author_id = null) {
        $this->setOrderId($order_id);
        $this->setResponse($response);
        $this->setCorrect($is_correct);
        $this->setAuthorId($author_id);

        $this->user_responses = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function setOrderId(int $order_id): void {
        $this->order_id = $order_id;
    }

    public function getOrderId(): int {
        return $this->order_id;
    }

    public function setAuthorId(?string $author_id): void {
        $this->author_id = $author_id;
    }

    public function getAuthorId(): ?string {
        return $this->author_id;
    }

    public function setResponse(string $response): void {
        $this->response = $response;
    }

    public function getResponse(): string {
        return $this->response;
    }

    public function setCorrect(bool $correct): void {
        $this->correct = $correct;
    }

    public function isCorrect(): bool {
        return $this->correct;
    }

    public function isCustom(): bool {
        return $this->author_id !== null;
    }

    public function setPoll(Poll $poll): void {
        $this->poll = $poll;
    }

    public function getPoll(): Poll {
        return $this->poll;
    }

    public function hasUserResponses(): bool {
        return count($this->user_responses) > 0;
    }

    /**
     * @return Collection<Response>
     */
    public function getUserResponses(): Collection {
        return $this->user_responses;
    }
}
