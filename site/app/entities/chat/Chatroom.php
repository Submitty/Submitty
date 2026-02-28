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

    public function calcAnonName(string $user_id, ?\Doctrine\ORM\EntityManagerInterface $em = null): string {
        $adjectives = ["Quick","Lazy","Cheerful","Pensive","Mysterious","Bright","Sly","Brave","Calm","Eager","Fierce","Gentle","Jolly","Kind","Lively","Nice","Proud","Quiet","Rapid","Swift"];
        $nouns = ["Duck","Goose","Swan","Eagle","Parrot","Owl","Sparrow","Robin","Pigeon","Falcon","Hawk","Flamingo","Pelican","Seagull","Cardinal","Canary","Finch","Hummingbird"];

        $fallbackWithSuffix = function () use ($user_id, $adjectives, $nouns): string {
            $session_started_at = $this->getSessionStartedAt() !== null ? $this->getSessionStartedAt()->format("Y-m-d H:i:s") : "unknown";
            $seed_string = $user_id . "-" . $this->getId() . "-" . $this->getHostId() . "-" . $session_started_at;
            $adj_hash = crc32($seed_string);
            $noun_hash = crc32(strrev($seed_string));
            $adj_index = abs($adj_hash) % count($adjectives);
            $noun_index = abs($noun_hash) % count($nouns);
            $suffix = strtoupper(substr(md5($seed_string), 0, 4));
            return "Anonymous {$adjectives[$adj_index]} {$nouns[$noun_index]} #{$suffix}";
        };

        if ($em === null) {
            return $fallbackWithSuffix();
        }

        try {
            $repository = $em->getRepository(ChatroomAnonymousName::class);
            $anonName = $repository->findOneBy([
                "chatroomId" => $this->getId(),
                "userId" => $user_id
            ]);

            if ($anonName !== null) {
                $stored = $anonName->getDisplayName();
                if (strpos($stored, ' #') === false || !preg_match('/ #[A-Fa-f0-9]{4}$/', $stored)) {
                    $adj = $adjectives[random_int(0, count($adjectives) - 1)];
                    $noun = $nouns[random_int(0, count($nouns) - 1)];
                    $suffix = strtoupper(bin2hex(random_bytes(2)));
                    $displayName = "Anonymous {$adj} {$noun} #{$suffix}";
                    $anonName->setDisplayName($displayName);
                    $em->flush();
                    return $displayName;
                }
                return $stored;
            }

            // Generate unique display name with retry logic for collisions
            $maxRetries = 5;
            $displayName = null;
            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                $adj = $adjectives[random_int(0, count($adjectives) - 1)];
                $noun = $nouns[random_int(0, count($nouns) - 1)];
                $suffix = strtoupper(bin2hex(random_bytes(2)));
                $candidateName = "Anonymous {$adj} {$noun} #{$suffix}";
                
                // Check if this name already exists in the chatroom
                $repo = $em->getRepository(ChatroomAnonymousName::class);
                $existing = $repo->createQueryBuilder('can')
                    ->where('can.chatroom_id = :chatroom_id')
                    ->andWhere('can.display_name = :display_name')
                    ->setParameter('chatroom_id', $this->getId())
                    ->setParameter('display_name', $candidateName)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($existing === null) {
                    $displayName = $candidateName;
                    break;
                }
            }
            
            // Fallback if max retries exceeded (very unlikely)
            if ($displayName === null) {
                $suffix = strtoupper(bin2hex(random_bytes(2)));
                $displayName = "Anonymous User #{$suffix}";
            }
            
            $anonName = new ChatroomAnonymousName($this->getId(), $user_id, $displayName);
            $em->persist($anonName);
            $em->flush();

            return $displayName;
        }
        catch (\Throwable $e) {
            return $fallbackWithSuffix();
        }
    }

    public function regenerateAllAnonNames(\Doctrine\ORM\EntityManagerInterface $em): void {
        $adjectives = ["Quick","Lazy","Cheerful","Pensive","Mysterious","Bright","Sly","Brave","Calm","Eager","Fierce","Gentle","Jolly","Kind","Lively","Nice","Proud","Quiet","Rapid","Swift"];
        $nouns = ["Duck","Goose","Swan","Eagle","Parrot","Owl","Sparrow","Robin","Pigeon","Falcon","Hawk","Flamingo","Pelican","Seagull","Cardinal","Canary","Finch","Hummingbird"];
        $usedNames = [];

        $repository = $em->getRepository(ChatroomAnonymousName::class);
        $anonNames = $repository->findBy(["chatroomId" => $this->getId()]);

        foreach ($anonNames as $anonName) {
            do {
                $adj = $adjectives[random_int(0, count($adjectives) - 1)];
                $noun = $nouns[random_int(0, count($nouns) - 1)];
                $suffix = strtoupper(bin2hex(random_bytes(2)));
                $displayName = "Anonymous {$adj} {$noun} #{$suffix}";
            } while (in_array($displayName, $usedNames, true));
            $usedNames[] = $displayName;
            $anonName->setDisplayName($displayName);
        }

        $em->flush();
    }
}
