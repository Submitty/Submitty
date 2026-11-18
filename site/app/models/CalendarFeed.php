<?php

declare(strict_types=1);

namespace app\models;

use app\entities\calendar\CalendarItem;
use app\entities\calendar\GlobalItem;
use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;

/**
 * Builds the iCalendar (RFC 5545) feed that calendar applications subscribe to.
 *
 * @phpstan-type FeedEvent array{uid: string, summary: string, start: \DateTimeInterface, all_day: bool, url: ?string, tentative: bool}
 */
class CalendarFeed {
    /**
     * The secret part of a user's feed link. Calendar applications cannot log in, so this is
     * the only credential. It changes when the user's API key changes, which resets the link.
     */
    public static function getSignature(Core $core, string $user_id): ?string {
        $api_key = $core->getQueries()->getSubmittyUserApiKey($user_id);
        if ($api_key === null) {
            return null;
        }
        return hash_hmac('sha256', "calendar-feed|{$user_id}|{$api_key}", $core->getConfig()->getSecretSession());
    }

    public static function getUrl(Core $core, string $user_id): string {
        return $core->buildUrl(['calendar', 'feed', $user_id, self::getSignature($core, $user_id) . '.ics']);
    }

    public static function courseKey(Course $course): string {
        return $course->getTerm() . '.' . $course->getTitle();
    }

    /**
     * Instructors can always open a gradeable, graders once TAs can view it, and everyone once submissions open.
     */
    public static function canOpen(Gradeable $gradeable, int $user_group): bool {
        return $user_group === User::GROUP_INSTRUCTOR
            || ($user_group < User::GROUP_STUDENT && $gradeable->isTaViewOpen())
            || $gradeable->isSubmissionOpen();
    }

    /**
     * @param string[]|null $course_keys courseKey() values to keep, or null for every course of the user.
     *                                   The options can only remove events, never add any.
     */
    public static function build(Core $core, string $user_id, ?array $course_keys, bool $show_gradeables, bool $show_items): string {
        $now = $core->getDateTimeNow();
        $host = parse_url($core->getConfig()->getBaseUrl(), PHP_URL_HOST);
        // "/" cannot occur in a term, a course name or a gradeable id, so the event ids below cannot collide.
        $events = [];
        $all_day = ['all_day' => true, 'url' => null, 'tentative' => false];

        $courses = $show_gradeables || $show_items ? $core->getQueries()->getCourseForUserId($user_id) : [];

        foreach ($courses as $course) {
            if ($course_keys !== null && !in_array(self::courseKey($course), $course_keys, true)) {
                continue;
            }
            $term = $course->getTerm();
            $title = $course->getTitle();
            $core->loadCourseConfig($term, $title);
            $core->loadCourseDatabase();

            if ($show_gradeables) {
                foreach ($core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                    if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE || !$gradeable->hasDueDate()) {
                        continue;
                    }
                    $can_open = self::canOpen($gradeable, $course->getUserGroup());
                    $events[] = [
                        'uid' => "gradeable/{$term}/{$title}/{$gradeable->getId()}@{$host}",
                        'summary' => "{$gradeable->getTitle()} ({$title})" . ($can_open ? '' : ' (not open yet)'),
                        'start' => $gradeable->getSubmissionDueDate(),
                        'all_day' => false,
                        'url' => $can_open ? $core->buildCourseUrl(['gradeable', $gradeable->getId()]) : null,
                        'tentative' => !$can_open,
                    ];
                }
            }
            if ($show_items) {
                foreach ($core->getCourseEntityManager()->getRepository(CalendarItem::class)->findAll() as $item) {
                    $events[] = [
                        'uid' => "item/{$term}/{$title}/{$item->getId()}@{$host}",
                        'summary' => "{$item->getText()} ({$title})",
                        'start' => $item->getDate(),
                    ] + $all_day;
                }
            }
            $core->getCourseDB()->disconnect();
        }
        $core->getConfig()->setCourseLoaded(false);

        if ($show_items) {
            foreach ($core->getSubmittyEntityManager()->getRepository(GlobalItem::class)->findAll() as $item) {
                $events[] = [
                    'uid' => "global-item/{$item->getId()}@{$host}",
                    'summary' => $item->getText(),
                    'start' => $item->getDate(),
                ] + $all_day;
            }
        }

        return self::render($events, $now);
    }

    /**
     * @param FeedEvent[] $events
     */
    public static function render(array $events, \DateTimeInterface $now): string {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Submitty//Calendar//EN', 'X-WR-CALNAME:Submitty'];
        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTAMP:' . self::utc($now);
            $lines[] = $event['all_day']
                ? 'DTSTART;VALUE=DATE:' . $event['start']->format('Ymd')
                : 'DTSTART:' . self::utc($event['start']);
            $lines[] = 'SUMMARY:' . self::escape($event['summary']);
            if ($event['url'] !== null) {
                $lines[] = 'URL:' . $event['url'];
                // Google Calendar ignores URL, so the link is also in the description.
                $lines[] = 'DESCRIPTION:' . self::escape($event['url']);
            }
            if ($event['tentative']) {
                // Calendar applications that support it show the event as tentative. Outlook reads its own property.
                $lines[] = 'STATUS:TENTATIVE';
                $lines[] = 'X-MICROSOFT-CDO-BUSYSTATUS:TENTATIVE';
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map(fn (string $line): string => self::fold($line), $lines)) . "\r\n";
    }

    private static function utc(\DateTimeInterface $date): string {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function escape(string $text): string {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\\;', '\\,', '\\n', '\\n', ''], $text);
    }

    /**
     * RFC 5545 limits a line to 75 octets. A longer line continues on the next line after one space.
     */
    private static function fold(string $line): string {
        $folded = mb_strcut($line, 0, 75, 'UTF-8');
        for ($pos = strlen($folded); $pos < strlen($line); $pos += strlen($chunk)) {
            $chunk = mb_strcut($line, $pos, 74, 'UTF-8');
            $folded .= "\r\n " . $chunk;
        }
        return $folded;
    }
}
