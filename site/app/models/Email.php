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
    /** @prop @var string Subject line of email */
    protected $subject;
    /** @prop @var string Body of email */
    protected $body;
    /** @prop @var string user name */
    protected $user_id;


  /**
   * Email constructor.
   *
   * @param Core  $core
   * @param array $details
   */

    public function __construct(Core $core, $details = []) {
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

        $author = $details["author"];
        $this->setBody($this->formatBody($details["body"], $relevant_url, $author));
    }

    //inject course label into subject
    private function formatSubject($subject) {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty $course]: " . $subject;
    }

    //inject a "do not reply" note in the footer of the body
    //also adds author and a relevant url if one exists
    private function formatBody($body, $relevant_url = null, $author = false) {
        $body .= "\n\n";
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] === "Anon") ? 1 : 0;
        if (!($anon) && $author) {
            $body .= "Author: " . $this->core->getUser()->getDisplayedFirstName() . " " . $this->core->getUser()->getDisplayedLastName()[0] . ".\n";
        }
        if (!is_null($relevant_url)) {
            $body .= "Click here for more info: " . $relevant_url;
        }
        return $body . "\n\n--\nNOTE: This is an automated email notification, which is unable to receive replies.\nPlease refer to the course syllabus for contact information for your teaching staff.";
    }
}
