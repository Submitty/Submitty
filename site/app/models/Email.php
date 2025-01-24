<?php

namespace app\models;

use app\libraries\Core;

/**
 * Class Email
 *
 * @method void     setSubject($sub)
 * @method void     setBody($bod)
 * @method void     setUserId($uid)

 * @method string   getSubject()
 * @method string   getBody()
 * @method string   getUserId()
 * @method string   getEmail()
 */
class Email extends AbstractModel {
    /** @prop
     * @var string Subject line of email
     */
    protected $subject;
    /** @prop
     * @var string Body of email
     */
    protected $body;
    /** @prop
     * @var string username of student.
     * Alternative option to providing an email address and to_name.
     */
    protected $user_id;
    /** @prop
     * @var string Email address.
     * Alternative option to providing a user_id. Should use to_name as well.
     */
    protected $email_address;
    /** @prop
     * @var string Name of who we're sending to.
     * Alternative option to providing a user_id.
     */
    protected $to_name;

    /**
     * Email constructor.
     * details must contain a subject, a body, and a user id or email address to send to.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, array $details = []) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }
        if (isset($details["to_user_id"])) {
            $this->setUserId($details["to_user_id"]);
        }
        else {
            $this->setEmailAddress($details["email_address"]);
            $this->setToName($details["to_name"]);
        }
        $this->setSubject($this->formatSubject($details["subject"]));
        $this->setBody($this->formatBody(
            $details["body"],
            $details['relevant_url'] ?? null,
            $details['author'] ?? false
        ));
    }

    //inject course label into subject
    private function formatSubject(string $subject): string {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty $course]: " . $subject;
    }

    //inject a "do not reply" note in the footer of the body
    //also adds author and a relevant url if one exists
    private function formatBody(string $body, ?string $relevant_url, bool $author): string {
        $extra = [];
        if (!(isset($_POST["Anon"]) && $_POST["Anon"] === "Anon") && $author) {
            $extra[] = "Author: " . $this->core->getUser()->getDisplayedGivenName() . " " . $this->core->getUser()->getDisplayedFamilyName()[0] . ".";
        }
        if (!is_null($relevant_url)) {
            $extra[] = "Click here for more info: " . $relevant_url;
        }

        if (count($extra) > 0) {
            $body .= "\n\n" . implode("\n", $extra);
        }
        return $body . "\n\n--\nNOTE: This is an automated email notification, which is unable to receive replies.\nPlease refer to the course syllabus for contact information for your teaching staff.";
    }
}
