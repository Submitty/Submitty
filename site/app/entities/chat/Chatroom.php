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
    // 50 adjectives × 50 nouns = 2,500 base name combinations.
    // Far exceeds typical class sizes, keeping numeric-suffix collisions rare.
    private const ADJECTIVES = [
        "Quick","Lazy","Cheerful","Pensive","Mysterious","Bright","Sly","Brave","Calm","Eager",
        "Fierce","Gentle","Jolly","Kind","Lively","Nice","Proud","Quiet","Rapid","Swift",
        "Bold","Clever","Daring","Earnest","Fancy","Graceful","Happy","Honest","Icy","Jumpy",
        "Keen","Loving","Merry","Noble","Odd","Peppy","Quirky","Rowdy","Silly","Tidy",
        "Unique","Vivid","Warm","Xenial","Youthful","Zany","Agile","Bouncy","Crafty","Dazzling",
    ];

    private const NOUNS = [
        "Duck","Goose","Swan","Eagle","Parrot","Owl","Sparrow","Robin","Pigeon","Falcon",
        "Hawk","Flamingo","Pelican","Seagull","Cardinal","Canary","Finch","Hummingbird","Crane","Heron",
        "Ibis","Kingfisher","Lark","Magpie","Nightjar","Oriole","Penguin","Quail","Raven","Stork",
        "Toucan","Vulture","Warbler","Xenops","Yellowthroat","Albatross","Bluebird","Cockatoo","Dove","Egret",
        "Partridge","Puffin","Roadrunner","Sandpiper","Thrush","Wren","Macaw","Kestrel","Booby","Jay",
    ];

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

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTime $session_started_at = null;

    /**
     * @var Collection<Message>
     */
    #[ORM\OneToMany(mappedBy: "chatroom", targetEntity: Message::class)]
    private Collection $messages;

    /**
     * @var Collection<ChatroomParticipant>
     */
    #[ORM\OneToMany(mappedBy: "chatroom", targetEntity: ChatroomParticipant::class, fetch: "EAGER")]
    private Collection $participants;

    public function __construct(UserEntity $host, string $title, string $description) {
        $this->host = $host;
        $this->setTitle($title);
        $this->setDescription($description);
        $this->messages = new ArrayCollection();
        $this->participants = new ArrayCollection();
        $this->is_active = false;
        $this->allow_anon = true;
        $this->is_deleted = false;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): UserEntity {
        return $this->host;
    }

    public function getHostId(): string {
        return $this->host->getId();
    }

    public function getHostName(): string {
        return $this->host->getDisplayFullName();
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

    public function getParticipants(): Collection {
        return $this->participants;
    }

    public function getSessionStartedAt(): ?\DateTime {
        return $this->session_started_at;
    }

    public function setSessionStartedAt(?\DateTime $session_started_at): void {
        $this->session_started_at = $session_started_at;
    }

    /**
     * Bumps session_started_at to now and clears all stored anonymous names so
     * they are re-derived on the next resolveAnonName() call.
     * Can be triggered by the instructor via the "Regenerate Names" button.
     */
    public function regenerateAnonNames(): void {
        $this->session_started_at = new \DateTime();
        foreach ($this->participants as $participant) {
            $participant->clearAnonName();
        }
    }

    /**
     * Derives a deterministic base anonymous name from the participant's stored
     * cryptographically random salt via HMAC-SHA256.
     * The salt is generated once at join time and never changes, so the mapping
     * is stable across cookie clears and re-logins.
     */
    private function deriveBaseAnonName(ChatroomParticipant $participant): string {
        $hmac = hash_hmac('sha256', $participant->getAnonSalt(), 'submitty-anon-name');
        $adj_index  = hexdec(substr($hmac, 0, 8))  % count(self::ADJECTIVES);
        $noun_index = hexdec(substr($hmac, 8, 8))  % count(self::NOUNS);
        return 'Anonymous ' . self::ADJECTIVES[$adj_index] . ' ' . self::NOUNS[$noun_index];
    }

    /**
     * Returns the set of anonymous names already assigned in the current session,
     * excluding the given participant.
     *
     * @return string[]
     */
    private function getTakenNamesThisSession(ChatroomParticipant $excluding): array {
        $snapshot = $this->session_started_at?->format('Y-m-d H:i:s') ?? 'unknown';
        $taken = [];
        foreach ($this->participants as $p) {
            if ($p === $excluding) {
                continue;
            }
            if ($p->getSessionSnapshot() === $snapshot && $p->getAnonName() !== null) {
                $taken[] = $p->getAnonName();
            }
        }
        return $taken;
    }

    /**
     * Resolves and caches the anonymous name for a participant in this chatroom session.
     *
     * - If the participant already has a name recorded for the current session, returns it.
     * - Otherwise, derives a base name from their permanent random salt, checks for
     *   collisions among other participants in this session, and appends a numeric suffix
     *   if necessary (e.g. "Anonymous Quick Duck 2").
     * - The resolved name is stored on the participant entity so the caller must flush
     *   the entity manager to persist it.
     */
    public function resolveAnonName(ChatroomParticipant $participant): string {
        $snapshot = $this->session_started_at?->format('Y-m-d H:i:s') ?? 'unknown';

        // Return cached name if it belongs to the current session.
        if (
            $participant->getAnonName() !== null
            && $participant->getSessionSnapshot() === $snapshot
        ) {
            return $participant->getAnonName();
        }

        $baseName = $this->deriveBaseAnonName($participant);
        $taken    = $this->getTakenNamesThisSession($participant);

        if (!in_array($baseName, $taken, true)) {
            $resolvedName = $baseName;
        }
        else {
            $suffix = 2;
            do {
                $resolvedName = "{$baseName} {$suffix}";
                $suffix++;
            } while (in_array($resolvedName, $taken, true));
        }

        $participant->setAnonName($resolvedName);
        $participant->setSessionSnapshot($snapshot);

        return $resolvedName;
    }


}
