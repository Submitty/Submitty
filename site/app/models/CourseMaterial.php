<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class CourseMaterial
 *
 * @method integer getId()
 * @method void setId($id)
 * @method int getType()
 * @method void setType($type)
 * @method string getPath()
 * @method void setPath($path)
 * @method string getLinkTitle()
 * @method void setLinkTitle($link_title)
 * @method string getLinkUrl()
 * @method void setLinkUrl($link_url)
 * @method \DateTime getReleaseDate()
 * @method void setReleaseDate($release_date)
 * @method bool getHiddenFromStudents()
 * @method void setHiddenFromStudents($hidden_from_students)
 * @method float getPriority()
 * @method void setPriority($priority)
 * @method bool getSectionLock()
 * @method void setSectionLock($section_lock)
 * @method array getSections()
 * @method void setSections($sections)
 */

class CourseMaterial extends AbstractModel {
    const FILE = 0;
    const LINK = 1;

    /** @prop @var int Type of the course material (0->file or 1->link) */
    protected $type;
    /** @prop @var string path of the course material if this is a file */
    protected $path;
    /** @prop @var \DateTime The release date for the course material */
    protected $release_date;
    /** @prop @var bool Whether or not this will be displayed to students */
    protected $hidden_from_students;
    /** @prop @var float Sorting priority for this item */
    protected $priority = 0;
    /** @prop @var bool Whether or not there is a section lock */
    protected $section_lock;
    /** @prop @var array Sections that can see this material */
    protected $sections = null;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->setType($details['type']);
        $this->setPath($details['path']);
        $this->setReleaseDate(DateUtils::parseDateTime($details['release_date'], $this->core->getUser()->getUsableTimeZone()));
        $this->setHiddenFromStudents($details['hidden_from_students']);
        $this->setPriority($details['priority']);
        $this->setSectionLock($details['section_lock']);
        if (isset($details['sections']) && $details['sections'][0] != null) {
            $this->setSections($details['sections']);
        }
    }

    public function isFile(): bool {
        return $this->getType() === self::FILE;
    }

    public function isLink(): bool {
        return $this->getType() === self::LINK;
    }

    public function isSectionAllowed(User $user): bool {
        return in_array($user->getRegistrationSection(), $this->sections);
    }
}
