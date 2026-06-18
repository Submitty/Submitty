<?php

declare(strict_types=1);

namespace app\entities\poll;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "poll_responses")]
class Response {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $id;

    #[ORM\Column(type: Types::TEXT)]
    protected string $student_id;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: "responses")]
    #[ORM\JoinColumn(name: "poll_id", referencedColumnName: "poll_id", nullable: false)]
    protected Poll $poll;

    #[ORM\ManyToOne(targetEntity: Option::class, fetch: "EAGER", inversedBy: "user_responses")]
    #[ORM\JoinColumn(name: "option_id", referencedColumnName: "option_id", nullable: false)]
    protected Option $option;

    public function __construct(string $student_id) {
        $this->setStudentId($student_id);
    }

    public function getId(): int {
        return $this->id;
    }

    private function setStudentId(string $student_id): void {
        $this->student_id = $student_id;
    }

    public function getStudentId(): string {
        return $this->student_id;
    }

    public function setPoll(Poll $poll): void {
        $this->poll = $poll;
    }

    public function getPoll(): Poll {
        return $this->poll;
    }

    public function setOption(Option $option): void {
        $this->option = $option;
    }

    public function getOption(): Option {
        return $this->option;
    }
}
