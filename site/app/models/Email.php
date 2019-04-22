<?php

namespace app\models;
use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class Email
 *
 * @method void     setSubject()
 * @method void     setBody()
 * @method void     setRecipient()

 * @method string   getSubject()
 * @method string   getBody()
 * @method string   getRecipient()
 */
class Email extends AbstractModel {

  /** @property @var string Subject line of email */
  protected $subject;
  /** @property @var string Body of email */
  protected $body;
  /** @property @var string Intended receiver of email */
  protected $recipient;

  /**
   * Email constructor.
   *
   * @param Core  $core
   * @param array $details
   */
  public function __construct(Core $core, $details=array()) {
      parent::__construct($core);
      if (count($details) == 0) {
          return;
      }

      $this->setRecipient($details["recipient"]);
      switch($details["type"]){
        case 'forum_announcement':
          $this->handleForumAnnouncement($details["email_subject"], $details["email_body"]);
        case 'seating_assignment':
          $this->handleSeatingAssignment($details["email_subject"], $details["email_body"]);
        default:
          break;

      }

    }

    private function handleForumAnnouncement($email_subject, $email_body) {
      $formatted_subject = $this->formatSubject($email_subject);
      $formatted_body = "An Instructor/TA made an announcement in the Submitty discussion forum:\n\n".$this->formatBody($email_body);

      $this->setSubject("usdflsudlnj");
      $this->setBody("askdfjnalsjdn");
    }

    private function handleSeatingAssignment($email_subject, $email_body) {
      $formatted_body = $this->formatBody($email_body);

      $this->setSubject($email_subject);
      $this->setBody($formatted_body);
    }

    private function formatSubject($email_subject) {
      $course = $this->core->getConfig()->getCourse();
      return "[Submitty $course] ".$email_subject;
    }

    private function formatBody($email_body){
      return $email_body."\n--\nNOTE: This is an automated email.\nAny responses will not be looked at or responded to.";
    }

}
