<?php

namespace app\entities\db;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="app\repositories\email\EmailRepository")
 * @ORM\Table(name="emails")
 * @method string getUserId()
 * @method string getSubject()
 * @method string getBody()
 * @method \DateTime getCreated()
 * @method \DateTime getSent()
 * @method string getError()
 * @method string getEmailAddress()
 * @method string getSemester()
 * @method string getCourse()
 */
class EmailEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var int
     */
    private $id;
    /** 
     * @ORM\Column(type="string")
     * @prop-read string
     */
    private $user_id;
    /**
     * @ORM\Column(type="text")
     * @prop-read string
     */
    private $subject;
    /** 
     * @ORM\Column(type="text")
     * @prop-read string
     */
    private $body;
    /**
     * @ORM\Column(type="datetime")
     * @prop-read DateTime
     */
    private $created;
    /**
     * @ORM\Column(type="datetime")
     * @prop-read DateTime
     */
    private $sent;
    /** 
     * @ORM\Column(type="string")
     * @prop-read string
     */
    private $error;
    /**
     * @ORM\Column(type="string", length=255)
     * @prop-read string
     */
    private $email_address;
    /** 
     * @ORM\Column(type="string")
     * @prop-read string
     */
    private $semester;
    /**
     * @ORM\Column(type="string")
     * @prop-read string
     */
    private $course;

    /**
     * @return string
     */
    public function getUserId(): string {
        return $this->user_id;
    }

    public function getSubject(): string {
        return $this->subject;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function getCreated(): \DateTime {
        return $this->created;
    }

    public function getSent(): \DateTime {
        return $this->sent;
    }

    public function getError(): string {
        return $this->error;
    }

    public function getEmailAddress(): string {
        return $this->email_address;
    }

    public function getSemester(): string {
        return $this->semester;
    }

    public function getCourse(): string {
        return $this->course;
    }
}
