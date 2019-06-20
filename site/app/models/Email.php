<?php

namespace app\models;
use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class Email
 *
 * @method void     setSubject($sub)
 * @method void     setBody($bod)
 * @method void     setUserId($uid)

 * @method string   getSubject()
 * @method string   getBody()
 * @method string   getUserId()
 */
class Email extends AbstractModel {
    /** @property @var string Subject line of email */
    protected $subject;
    /** @property @var string Body of email */
    protected $body;
    /** @property @var string user name */
    protected $user_id;


  /**
   * Email constructor.
   *
   * @param Core  $core
   * @param array $details
   */

    public function __construct(Core $core,array $event) {
        parent::__construct($core);
        if (count($event) == 0) {
            return;
        }
        $this->setUserId($event["user_id"]);
        $this->setSubject($this->formatSubject($event["subject"]));
        $this->setBody($this->formatBody($event["content"]));
    }

    //inject course label into subject
    private function formatSubject($subject) {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty $course]: ".$subject;
    }

    //inject a "do not reply" note in the footer of the body
    private function formatBody($body){
        return $body."\n--\nNOTE: This is an automated email.\nAny responses will not be looked at or responded to.";
    }

}
