<?php

declare(strict_types=1);

namespace app\entities\chat;

use app\entities\UserEntity;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatrooms")]
class Chatroom {
    #[ORM\Id]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: UserEntity::class, fetch: "EAGER")]
    #[ORM\JoinColumn(name: "host_id", referencedColumnName: "user_id", nullable: false)]
    private UserEntity $host;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::STRING)]
    private string $description;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_active;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_deleted;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allow_anon;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allow_read_only_after_end = false;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTime $session_started_at = null;

    /**
     * @var Collection<Message>
     */
    #[ORM\OneToMany(mappedBy: "chatroom", targetEntity: Message::class)]
    private Collection $messages;

    public function __construct(UserEntity $host, string $title, string $description) {
        $this->host = $host;
        $this->setTitle($title);
        $this->setDescription($description);
        $this->messages = new ArrayCollection();
        $this->is_active = false;
        $this->allow_anon = true;
        $this->is_deleted = false;
        $this->allow_read_only_after_end = false;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): UserEntity {
        return $this->host;
    }

    public function setHost(UserEntity $host): void {
        $this->host = $host;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setTitle(string $title): void {
        $this->title = $title;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function toggleActiveStatus(): void {
        $this->is_active = !$this->is_active;
    }

    public function isActive(): bool {
        return $this->is_active;
    }

    public function isAllowAnon(): bool {
        return $this->allow_anon;
    }

    public function setAllowAnon(bool $allow_anon): void {
        $this->allow_anon = $allow_anon;
    }

    public function getMessages(): Collection {
        return $this->messages;
    }

    public function addMessage(Message|null $message): void {
        $this->messages->add($message);
    }

    public function deleteChat(): void {
        $this->is_deleted = true;
    }

    public function chatDeleted(): bool {
        return $this->is_deleted;
    }

    public function getHostId(): string {
        return $this->host->getId();
    }

    public function getHostName(): string {
        return $this->host->getDisplayFullName();
    }

    public function getSessionStartedAt(): ?\DateTime {
        return $this->session_started_at;
    }

    public function setSessionStartedAt(?\DateTime $session_started_at): void {
        $this->session_started_at = $session_started_at;
    }

    public function allowReadOnlyAfterEnd(): bool {
        return $this->allow_read_only_after_end;
    }

    public function setAllowReadOnlyAfterEnd(bool $allow): void {
        $this->allow_read_only_after_end = $allow;
    }

    public function isReadOnly(): bool {
        return !$this->isActive() && $this->allowReadOnlyAfterEnd();
    }

    public function calcAnonName(string $user_id, string $global_secret): string {
        $adjectives = [
            "Quick", "Lazy", "Cheerful", "Pensive", "Mysterious", "Bright", "Sly", "Brave",
            "Calm", "Eager", "Fierce", "Gentle", "Jolly", "Kind", "Lively", "Nice",
            "Proud", "Quiet", "Rapid", "Swift", "Bold", "Clever", "Daring", "Fancy",
            "Happy", "Keen", "Noble", "Polite", "Silly", "Wise", "Brisk", "Crisp",
            "Grand", "Neat", "Rich", "Warm", "Cold", "Dark", "Deep", "Fair",
            "Good", "High", "Just", "Pure", "Rare", "True", "Vast", "Wild",
            "Cool", "Firm", "Free", "Glad", "Hard", "Loud", "Mild", "Real",
            "Safe", "Sure", "Tough", "Huge", "Smart", "Bland", "Stark", "Plump"
        ]; // 64 items

        $nouns = [
            "Duck", "Goose", "Swan", "Eagle", "Parrot", "Owl", "Sparrow", "Robin",
            "Pigeon", "Falcon", "Hawk", "Flamingo", "Pelican", "Seagull", "Cardinal", "Canary",
            "Finch", "Hummingbird", "Ostrich", "Penguin", "Stork", "Woodpecker", "Crow", "Raven",
            "Peacock", "Vulture", "Crane", "Heron", "Dove", "Jay", "Magpie", "Wren",
            "Quail", "Lark", "Gull", "Tern", "Kite", "Swift", "Cuckoo", "Puffin",
            "Ibis", "Egret", "Macaw", "Toucan", "Starling", "Oriole", "Weaver", "Shrike",
            "Thrush", "Martin", "Grouse", "Pheasant", "Bustard", "Rhea", "Emu", "Kiwi",
            "Moa", "Dodo", "Rook", "Fowl", "Chough", "Snipe", "Knot", "Teal"
        ]; // 64 items

        $index = abs(crc32($user_id));

        $session_started_at = $this->getSessionStartedAt() !== null ? $this->getSessionStartedAt()->format("Y-m-d H:i:s") : "unknown";
        $info = "chatroom_" . $this->getId() . "_" . $session_started_at;

        $randomizer_key = hash_hkdf('sha256', $global_secret, 32, $info, '');

        $a = ($index >> 16) & 0xFFFF;
        $b = $index & 0xFFFF;

        for ($i = 0; $i < 10; $i++) {
            $hmac = hash_hmac('sha256', $i . '+' . $b, $randomizer_key, true);
            $hash_val = (ord($hmac[0]) << 8) | ord($hmac[1]);
            $a = ($a ^ $hash_val) & 0xFFFF;
            $temp = $a;
            $a = $b;
            $b = $temp;
        }

        $final_index = (($a << 16) | $b) % 4096;
        $adj_idx = intdiv($final_index, 64);
        $noun_idx = $final_index % 64;

        return "Anonymous {$adjectives[$adj_idx]} {$nouns[$noun_idx]}";
    }
}
