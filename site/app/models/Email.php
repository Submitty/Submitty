<?php

namespace app\models;
use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class Email
 *
 * @method void     setSubject($sub)
 * @method void     setBody($bod)
 * @method void     setSenderId($sid)
 * @method void     setRecipient($recip)

 * @method string   getSubject()
 * @method string   getBody()
 * @method string   getUserId()
 * @method string   getRecipient()
 */
class Email extends AbstractModel {
    /** @property @var string Subject line of email */
    protected $subject;
    /** @property @var string Body of email */
    protected $body;
    /** @property @var string sender's user id */
    protected $sender_id;

    /** @property @var string Intended receiver of email */
    // NOTE: THIS IS ESSENTIALLY A DEPRECATED / LEGACY FIELD
    protected $recipient;


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
        $this->setSenderId($event["sender_id"]);
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
