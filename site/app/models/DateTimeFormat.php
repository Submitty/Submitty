<?php

namespace app\models;

use app\exceptions\BadArgumentException;
use app\libraries\Core;

class DateTimeFormat extends AbstractModel {

    // Set of legal specifiers
    const SPECIFIERS = ['MDY', 'DMY'];

    // Keys available to use with getDateFormat
    const DATE_FORMATS_KEYS = [
        'gradeable',
        'gradeable_with_seconds',
        'forum',
        'notification',
        'solution_ta_notes',
        'office_hours_queue',
        'date_time_picker',
        'late_days_allowed',
        'poll'
    ];

    // Internationalized DateTime formatting strings
    const DATE_FORMATS = [
        'MDY' => [
            'gradeable' => 'm/d/Y @ h:i A T',
            'gradeable_with_seconds' => 'm/d/Y @ h:i:s A T',
            'forum' => 'n/j g:i A',
            'notification' => 'n/j g:i A',
            'solution_ta_notes' => 'j/n g:i A',
            'office_hours_queue' => 'g:i A',
            'date_time_picker' => 'Y-m-d H:i:s',
            'late_days_allowed' => 'm/d/Y h:i:s A T',
            'poll' => 'm/d/Y'
        ],
        'DMY' => [
            'gradeable' => 'd/m/Y @ h:i A T',
            'gradeable_with_seconds' => 'd/m/Y @ h:i:s A T',
            'forum' => 'j/n g:i A',
            'notification' => 'j/n g:i A',
            'solution_ta_notes' => 'j/n g:i A',
            'office_hours_queue' => 'g:i A',
            'date_time_picker' => 'Y-m-d H:i:s',
            'late_days_allowed' => 'm/d/Y h:i:s A T',
            'poll' => 'd/m/Y'
        ]
    ];

    /** @var string Specifies which set of formatting strings to use */
    protected $specifier;

    /**
     * DateTimeFormat constructor
     *
     * @param Core $core
     * @param string $specifier A valid specifier string found in self::SPECIFIERS
     */
    public function __construct(Core $core, string $specifier) {
        parent::__construct($core);

        if (in_array($specifier, self::SPECIFIERS)) {
            $this->specifier = $specifier;
        }
        else {
            throw new BadArgumentException('Illegal $specifier passed to DateTimeFormat constructor.');
        }
    }

    /**
     * Get the appropriate internationalized DateTime formatting string.  Some countries use a MM/DD/YYYY format, while
     * others use a DD/MM/YYYY format, etc.
     *
     * @param string $key A key available in DateTimeFormat::DATE_FORMAT_KEYS
     * @throws BadArgumentException The supplied key was not found is DateTimeFormat::DATE_FORMATS_KEYS
     * @return string
     */
    public function getFormat(string $key) {
        if (!in_array($key, self::DATE_FORMATS_KEYS)) {
            throw new BadArgumentException('The $key must be a member DateTimeFormat::DATE_FORMAT_KEYS');
        }

        return self::DATE_FORMATS[$this->specifier][$key];
    }
}
