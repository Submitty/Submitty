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

    public function __construct(Core $core, $details = array()) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }
        $this->setUserId($details["to_user_id"]);
        $this->setSubject($this->formatSubject($details["subject"]));

        $relevant_url = null;
        if (array_key_exists("relevant_url", $details)) {
            $relevant_url = $details["relevant_url"];
        }
        $this->setBody($this->formatBody($details["body"], $relevant_url));
    }

    //inject course label into subject
    private function formatSubject($subject) {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty $course]: " . $subject;
    }

    //inject a "do not reply" note in the footer of the body
    //also adds a relevant url if one exists
    private function formatBody($body, $relevant_url = null) {
        if (!is_null($relevant_url)) {
            $body .= "\n\nClick here for more info: " . $relevant_url;
        }
        return $body . "\n\n--\nNOTE: This is an automated email notification, which is unable to receive replies.\nPlease refer to the course syllabus for contact information for your teaching staff.";
    }
}
