<?php

namespace app\models;

use app\libraries\Core;
use app\views\NavigationView;

/**
 * Class CalendarInfo
 *
 * Container of information used to fill the calendar.
 */
class GlobalCalendarInfo extends CalendarInfo {
    /**
     * Loads global calendar items into the calendar info.
     *
     * @param Core $core
     * @param array $global_calendar_items container of global calendar items
     * @return GlobalCalendarInfo
     */
    public static function loadGlobalCalendarInfo(Core $core): GlobalCalendarInfo {
        $info = new GlobalCalendarInfo($core);
        $global_calendar_items = $core->getSubmittyEntityManager()->getRepository(GlobalItem::class)->findAll();
        foreach ($global_calendar_items as $item) {
            $date = $item['date']; 
            $curItem = [
                'title' => htmlspecialchars($item['text']),
                'status' => "ann",
                'course' => 'Superuser',
                'semester' => 'N/A', // Main database items might not have a semester
                'icon' => '',
                'url' => '',
                'show_due' => false,
                'submission' => '',
                'status_note' => '',
                'color' => 'default_color', //maybe a red color represent this is a global item
                'type' => 'item',
                'date' => $date,
                'id' => $item['id']
            ];
            $info->items_by_date[$date][] = $curItem;
        }

        $info->empty_message = "There are currently no assignments posted.  Please check back later.";

        return $info;
    }

    /**
     * @return array<string, array<string,bool|string>>>
     */
    public function getItemsByDate(): array {
        return $this->items_by_date;
    }

    public function getEmptyMessage(): string {
        return $this->empty_message;
    }

    public function getColors(): array {
        return $this->colors;
    }
}
