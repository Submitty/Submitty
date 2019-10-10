<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class Notification
 *
 * @method void     setViewOnly($view_only)
 * @method void     setId($id)
 * @method void     setComponent($component)
 * @method void     setSeen($isSeen)
 * @method void     setElapsedTime($duration)
 * @method void     setCreatedAt($time)
 * @method void     setNotifyMetadata($metadata)
 * @method void     setNotifyContent($content)
 * @method void     setNotifySource($content)
 * @method void     setNotifyTarget($content)
 * @method void     setType($t)
 *
 * @method bool     isViewOnly()
 * @method int      getId()
 * @method string   getComponent()
 * @method bool     isSeen()
 * @method real     getElapsedTime()
 * @method string   getCreatedAt()
 * @method string   getCurrentUser()
 *
 * @method string   getNotifySource()
 * @method string   getNotifyTarget()
 * @method string   getNotifyContent()
 * @method string   getNotifyMetadata()
 * @method bool     getNotifyNotToSource()
 * @method string   getType()
 */
class OfficeHoursQueue extends AbstractModel {
    /**
     * Notifications constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }
}
