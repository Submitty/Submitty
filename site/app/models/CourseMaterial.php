<?php


namespace app\models;


use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class CourseMaterial
 *
 * @method integer getId()
 * @method void setId($id)
 * @method string getType()
 * @method void setType($type)
 * @method string getUrl()
 * @method void setUrl($url)
 * @method string getLinkTitle()
 * @method void setLinkTitle($link_title)
 * @method string getLinkUrl()
 * @method void setLinkUrl($link_url)
 * @method \DateTime getReleaseDate()
 * @method void setReleaseDate($release_date)
 * @method bool getHiddenFromStudents()
 * @method void setHiddenFromStudents($hidden_from_students)
 * @method int getPriority()
 * @method void setPriority($priority)
 * @method bool getSectionLock()
 * @method void setSectionLock($section_lock)
 * @method array getSections()
 * @method void setSections($sections)
 */

class CourseMaterial extends AbstractModel {
    /** @prop @var integer The id of the material in the DB */
    protected $id;
    /** @prop @var string Type of the course material (file or link) */
    protected $type;
    /** @prop @var string URL of the course material if this is a file */
    protected $url;
    /** @prop @var string Title of link course material type */
    protected $link_title;
    /** @prop @var string URL of the link course material type */
    protected $link_url;
    /** @prop @var \DateTime The release date for the course material */
    protected $release_date;
    /** @prop @var bool Whether or not this will be displayed to students */
    protected $hidden_from_students;
    /** @prop @var int Sorting priority for this item */
    protected $priority = 0;
    /** @prop @var bool Whether or not there is a section lock */
    protected $section_lock;
    /** @prop @var array Sections that can see this material */
    protected $sections = null;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);
        $this->setId($details['id']);
        $this->setType($details['type']);
        $this->setUrl($details['url']);
        $this->setLinkTitle($details['link_title']);
        $this->setLinkUrl($details['link_url']);
        $this->setReleaseDate(DateUtils::parseDateTime($details['release_date'], $this->core->getUser()->getUsableTimeZone()));
        $this->setHiddenFromStudents($details['hidden_from_students']);
        $this->setPriority($details['priority']);
        $this->setSectionLock($details['section_lock']);
        if (isset($details['sections']) && $details['sections'][0] != null) {
            $this->setSections($details['sections']);
        }
    }


}
