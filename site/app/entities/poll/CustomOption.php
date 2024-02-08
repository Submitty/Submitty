<?php

declare(strict_types=1);

namespace app\entities\poll;

use app\repositories\poll\OptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OptionRepository::class)]
#[ORM\Table(name: "poll_options_custom")]
class CustomOption {
    #[ORM\Id]
    #[ORM\Column(name: "option_id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(name: "order_id", type: Types::INTEGER)]
    protected int $order_id;

    #[ORM\Column(name: "author_id", type: Types::TEXT)]
    protected string $author_id;

    #[ORM\Column(name: "response", type: Types::TEXT)]
    protected string $response;

    #[ORM\Column(name: "credit", type: Types::BOOLEAN)]
    protected bool $credit;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: "options")]
    #[ORM\JoinColumn(name: "poll_id", referencedColumnName: "poll_id", nullable: false)]
    protected Poll $poll;

    /**
     * @var Collection<Response>
     */
    #[ORM\OneToMany(mappedBy: "option", targetEntity: Response::class)]
    #[ORM\JoinColumn(name: "option_id", referencedColumnName: "option_id")]
    protected Collection $user_responses;

    public function __construct(int $order_id, string $response, bool $credit, string $author_id) {
        $this->setOrderId($order_id);
        $this->setResponse($response);
        $this->setCredit($credit);
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

    public function setAuthorId(string $author_id) : void {
        $this->author_id = $author_id;
    }

    public function getAuthorId() : string {
        return $this->author_id;
    }

    public function setResponse(string $response): void {
        $this->response = $response;
    }

    public function getResponse(): string {
        return $this->response;
    }

    public function setCredit(bool $credit): void {
        $this->credit = $credit;
    }

    public function isCredit(): bool {
        return $this->credit;
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
