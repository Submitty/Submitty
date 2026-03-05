<?php

namespace tests\app\entities\chat;

use app\entities\chat\Chatroom;
use app\entities\chat\ChatroomParticipant;
use app\entities\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;
use ReflectionClass;
use ReflectionProperty;
use tests\BaseUnitTest;

/**
 * Unit tests for anonymous-name logic in Chatroom / ChatroomParticipant.
 *
 * Because Chatroom and UserEntity are Doctrine ORM entities with no public
 * constructor parameters for IDs, we use ReflectionClass / ReflectionProperty
 * to inject values the same way the rest of the entity test suite does.
 */
class ChatroomTester extends BaseUnitTest {

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Creates a minimal UserEntity with just the fields that Chatroom uses.
     */
    private function makeUser(string $userId, string $givenName = 'Test', string $familyName = 'User'): UserEntity {
        $ref  = new ReflectionClass(UserEntity::class);
        $user = $ref->newInstanceWithoutConstructor();

        $this->setPrivate($user, 'user_id', $userId);
        $this->setPrivate($user, 'user_givenname', $givenName);
        $this->setPrivate($user, 'user_familyname', $familyName);
        $this->setPrivate($user, 'user_preferred_givenname', null);
        $this->setPrivate($user, 'user_preferred_familyname', null);

        return $user;
    }

    /**
     * Creates a Chatroom with a given numeric id (bypassing the DB generator).
     */
    private function makeChatroom(int $id, UserEntity $host): Chatroom {
        $room = new Chatroom($host, 'Test Room', 'A test room');
        $this->setPrivate($room, 'id', $id);
        return $room;
    }

    /**
     * Creates a ChatroomParticipant with a fixed salt so tests are deterministic.
     */
    private function makeParticipant(Chatroom $room, UserEntity $user, string $fixedSalt): ChatroomParticipant {
        $participant = new ChatroomParticipant($room, $user);
        $this->setPrivate($participant, 'anon_salt', $fixedSalt);
        return $participant;
    }

    /**
     * Sets an inaccessible (private/protected) property on an object.
     */
    private function setPrivate(object $obj, string $property, mixed $value): void {
        $prop = new ReflectionProperty($obj, $property);
        $prop->setAccessible(true);
        $prop->setValue($obj, $value);
    }

    /**
     * Adds a ChatroomParticipant directly into the Chatroom's participants
     * ArrayCollection, simulating what Doctrine does when the relationship is
     * managed by the ORM.
     */
    private function addParticipantToRoom(Chatroom $room, ChatroomParticipant $participant): void {
        $prop = new ReflectionProperty($room, 'participants');
        $prop->setAccessible(true);
        /** @var ArrayCollection $collection */
        $collection = $prop->getValue($room);
        $collection->add($participant);
    }

    // -----------------------------------------------------------------------
    // ChatroomParticipant – unit tests
    // -----------------------------------------------------------------------

    public function testParticipantSaltIsNonEmptyOnConstruction(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $user        = $this->makeUser('student1');
        $participant = new ChatroomParticipant($room, $user);

        $this->assertNotEmpty($participant->getAnonSalt());
    }

    public function testParticipantSaltIs64HexChars(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $participant = new ChatroomParticipant($room, $this->makeUser('s1'));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $participant->getAnonSalt());
    }

    public function testParticipantAnonNameStartsNull(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $participant = new ChatroomParticipant($room, $this->makeUser('s1'));

        $this->assertNull($participant->getAnonName());
        $this->assertNull($participant->getSessionSnapshot());
    }

    public function testClearAnonNameResetsNameAndSnapshot(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $participant = new ChatroomParticipant($room, $this->makeUser('s1'));

        $participant->setAnonName('Anonymous Quick Duck');
        $participant->setSessionSnapshot('2024-01-01 10:00:00');
        $participant->clearAnonName();

        $this->assertNull($participant->getAnonName());
        $this->assertNull($participant->getSessionSnapshot());
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – determinism
    // -----------------------------------------------------------------------

    public function testResolveAnonNameIsDeterministic(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));

        $p1 = $this->makeParticipant($room, $this->makeUser('u1'), 'aaaa');
        $p2 = $this->makeParticipant($room, $this->makeUser('u1'), 'aaaa');

        $name1 = $room->resolveAnonName($p1);
        $name2 = $room->resolveAnonName($p2);

        $this->assertSame($name1, $name2, 'Same salt in the same session must always produce the same name');
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – output format
    // -----------------------------------------------------------------------

    public function testResolveAnonNameStartsWithAnonymous(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));
        $p    = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('ab', 32));

        $name = $room->resolveAnonName($p);

        $this->assertStringStartsWith('Anonymous ', $name);
    }

    public function testResolveAnonNameIsNonEmpty(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));
        $p    = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('cd', 32));

        $this->assertNotEmpty($room->resolveAnonName($p));
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – caching within a session
    // -----------------------------------------------------------------------

    public function testResolveAnonNameIsCachedForSameSession(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 09:00:00'));
        $p    = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('ef', 32));

        $name1 = $room->resolveAnonName($p);
        // Second call must hit the cache; also the participant entity must have been mutated
        $name2 = $room->resolveAnonName($p);

        $this->assertSame($name1, $name2, 'Name must be stable within the same session');
        $this->assertSame($name1, $p->getAnonName(), 'Participant entity should cache the resolved name');
    }

    public function testSnapshotIsStoredOnParticipantAfterResolution(): void {
        $sessionTime = '2024-06-01 08:00:00';
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime($sessionTime));
        $p           = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('12', 32));

        $room->resolveAnonName($p);

        $this->assertSame($sessionTime, $p->getSessionSnapshot());
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – new session clears the old name
    // -----------------------------------------------------------------------

    public function testNameChangesAfterSessionRegeneration(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 09:00:00'));
        $p    = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('ab', 32));
        $this->addParticipantToRoom($room, $p);

        $snapshotBefore = '2024-01-01 09:00:00';
        $room->resolveAnonName($p);
        $this->assertSame($snapshotBefore, $p->getSessionSnapshot());

        // Simulate the instructor clicking "Regenerate Names"
        $room->regenerateAnonNames();

        // After regeneration the participant's cached name is cleared.
        $this->assertNull($p->getAnonName(), 'Cached name must be cleared by regenerateAnonNames');

        // Re-resolving assigns a new snapshot (the bumped session time).
        $nameAfter = $room->resolveAnonName($p);
        $this->assertStringStartsWith('Anonymous ', $nameAfter);
        $this->assertNotSame(
            $snapshotBefore,
            $p->getSessionSnapshot(),
            'Session snapshot must reflect the new session_started_at after regeneration'
        );
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – uniqueness / no duplicates
    // -----------------------------------------------------------------------

    public function testNoDuplicateNamesForDistinctSaltsInSameSession(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-06-01 08:00:00'));

        $names = [];
        for ($i = 0; $i < 20; $i++) {
            // Each participant gets a unique, truly random salt (as in production)
            $p = new ChatroomParticipant($room, $this->makeUser("user{$i}"));
            $this->addParticipantToRoom($room, $p);
            $names[] = $room->resolveAnonName($p);
        }

        $this->assertCount(
            count($names),
            array_unique($names),
            'Each participant in the same chatroom session must receive a unique anonymous name'
        );
    }

    public function testCollisionResolutionAppendsNumericSuffix(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));

        // Both participants share the same salt → same base name → collision.
        $sharedSalt = str_repeat('de', 32);
        $p1 = $this->makeParticipant($room, $this->makeUser('u1'), $sharedSalt);
        $p2 = $this->makeParticipant($room, $this->makeUser('u2'), $sharedSalt);
        $this->addParticipantToRoom($room, $p1);
        $this->addParticipantToRoom($room, $p2);

        $name1 = $room->resolveAnonName($p1);
        $name2 = $room->resolveAnonName($p2);

        $this->assertNotSame($name1, $name2, 'Colliding base names must be disambiguated');
        // The second name should be the first name suffixed with " 2"
        $this->assertSame("{$name1} 2", $name2, 'Suffix must be " 2" for the first collision');
    }

    public function testThreeWayCollisionProducesCorrectSuffixes(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));

        $sharedSalt = str_repeat('ff', 32);
        $p1 = $this->makeParticipant($room, $this->makeUser('u1'), $sharedSalt);
        $p2 = $this->makeParticipant($room, $this->makeUser('u2'), $sharedSalt);
        $p3 = $this->makeParticipant($room, $this->makeUser('u3'), $sharedSalt);
        $this->addParticipantToRoom($room, $p1);
        $this->addParticipantToRoom($room, $p2);
        $this->addParticipantToRoom($room, $p3);

        $name1 = $room->resolveAnonName($p1);
        $name2 = $room->resolveAnonName($p2);
        $name3 = $room->resolveAnonName($p3);

        $this->assertSame("{$name1} 2", $name2);
        $this->assertSame("{$name1} 3", $name3);
    }

    // -----------------------------------------------------------------------
    // resolveAnonName – session-less chatroom (null session_started_at)
    // -----------------------------------------------------------------------

    public function testResolveAnonNameWorksWhenSessionStartedAtIsNull(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        // Leave session_started_at as null (default)
        $p    = $this->makeParticipant($room, $this->makeUser('u1'), str_repeat('00', 32));

        $name = $room->resolveAnonName($p);

        $this->assertStringStartsWith('Anonymous ', $name);
        $this->assertSame('unknown', $p->getSessionSnapshot());
    }

    // -----------------------------------------------------------------------
    // regenerateAnonNames
    // -----------------------------------------------------------------------

    public function testRegenerateAnonNamesClearsAllParticipantNames(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setSessionStartedAt(new \DateTime('2024-01-01 10:00:00'));

        // Resolve names for three participants so the cache is warm.
        // Participants must be in the room's collection so regenerateAnonNames()
        // can iterate over them.
        $participants = [];
        for ($i = 0; $i < 3; $i++) {
            $p = new ChatroomParticipant($room, $this->makeUser("u{$i}"));
            $this->addParticipantToRoom($room, $p);
            $room->resolveAnonName($p);
            $participants[] = $p;
        }

        $room->regenerateAnonNames();

        foreach ($participants as $p) {
            $this->assertNull($p->getAnonName(), 'All cached names must be cleared after regeneration');
            $this->assertNull($p->getSessionSnapshot(), 'All session snapshots must be cleared after regeneration');
        }
    }

    public function testRegenerateAnonNamesBumpsSessionStartedAt(): void {
        $host        = $this->makeUser('host1');
        $room        = $this->makeChatroom(1, $host);
        $oldTime     = new \DateTime('2024-01-01 10:00:00');
        $room->setSessionStartedAt($oldTime);

        $before = $room->getSessionStartedAt()?->format('Y-m-d H:i:s');
        $room->regenerateAnonNames();
        $after  = $room->getSessionStartedAt()?->format('Y-m-d H:i:s');

        $this->assertNotSame($before, $after, 'regenerateAnonNames() must update session_started_at');
    }

    // -----------------------------------------------------------------------
    // Chatroom state helpers (non-anon, but verifying entity correctness)
    // -----------------------------------------------------------------------

    public function testNewChatroomIsInactiveByDefault(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);

        $this->assertFalse($room->isActive());
    }

    public function testNewChatroomAllowsAnonByDefault(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);

        $this->assertTrue($room->isAllowAnon());
    }

    public function testNewChatroomIsNotDeletedByDefault(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);

        $this->assertFalse($room->chatDeleted());
    }

    public function testToggleActiveStatusFlipsState(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);

        $this->assertFalse($room->isActive());
        $room->toggleActiveStatus();
        $this->assertTrue($room->isActive());
        $room->toggleActiveStatus();
        $this->assertFalse($room->isActive());
    }

    public function testIsReadOnlyOnlyWhenInactiveAndAllowReadOnly(): void {
        $host = $this->makeUser('host1');
        $room = $this->makeChatroom(1, $host);
        $room->setAllowReadOnlyAfterEnd(true);

        // inactive + allow_read_only_after_end  → read-only
        $this->assertTrue($room->isReadOnly());

        // active + allow_read_only_after_end → NOT read-only
        $room->toggleActiveStatus();
        $this->assertFalse($room->isReadOnly());
    }
}

