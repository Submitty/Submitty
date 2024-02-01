<?php

namespace app\models;

use app\libraries\Core;
use app\entities\calendar\GlobalItem;

/**
 * Class CalendarInfo
 *
 * Container of information used to fill the calendar.
 */
class GlobalCalendarInfo extends AbstractModel {
    /**
     * @var array<string, array<string, string|bool>>
     * the structure of the array is a "YYYY-mm-dd" date string as key, and value
     * contains an array with a structure of
     * 'gradeable_id' => string   (the id of the item, reserved row and useless for now)
     * 'title'        => string   (the title of the item which will be shown on each clickable button)
     * 'semester'     => string   (the semester of which the item belongs)
     * 'course'       => string   (the title of the course of which the item belongs)
     * 'url'          => string   (the url of the clickable button)
     * 'onclick'      => string   (the onclick js function of the clickable button)
     * 'submission'   => string   (the timestamp of the item, shown in the popup tooltip)
     * 'status'       => string   (the status of the gradeable, open/closed/grading..., is used to show different
     *                             colors of item, relation between color and integer are recorded in css)
     * 'status_note'  => string   (a string describing this status)
     * 'grading_open' => string   (reserved, useless for now. Can be empty)
     * 'grading_due'  => string   (reserved, useless for now. Can be empty)
     * 'show_due'     => bool     (whether to show the due date when mouse is hovering over),
     * 'icon'         => string   (the icon showed before the item),
     */
    private $items_by_date = [];

    /** @var string */
    private $empty_message = "";

    /**
     * Loads global calendar items.
     *
     * @param Core $core
     * @return GlobalCalendarInfo with item infos
     */
    public static function loadGlobalCalendarInfo(Core $core) {
        $global_calendar_items = $core->getSubmittyEntityManager()->getRepository(GlobalItem::class)->findAll();

        $info = new GlobalCalendarInfo($core);
        foreach ($global_calendar_items as $item) {
            $date = $item->getDate()->format('Y-m-d');
            $curItem = [
                'title' => htmlspecialchars($item->getText()),
                'status' => $item->getTypeString(),
                'course' => 'Superuser',
                'semester' => '',
                'icon' => '',
                'url' => '',
                'show_due' => false,
                'submission' => '',
                'status_note' => '',
                'color' => '',
                'type' => 'item',
                'date' => $date,
                'id' => $item->getId()
            ];
            $info->items_by_date[$date][] = $curItem;
        }

        $info->empty_message = "There are currently no assignments posted. Please check back later.";

        return $info;
    }

    /**
     * @return array<string, array<string,bool|string>>
     */
    public function getGlobalItemsByDate(): array {
        return $this->items_by_date;
    }

    public function getGlobalEmptyMessage(): string {
        return $this->empty_message;
    }
}
