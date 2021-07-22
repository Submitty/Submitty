<?php

namespace app\entities\db;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
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
    /** @ORM\Column(type="string") */
    private $user_id;
    /** @ORM\Column(type="text") */
    private $subject;
    /** @ORM\Column(type="text") */
    private $body;
    /** @ORM\Column(type="datetime") */
    private $created;
    /** @ORM\Column(type="datetime") */
    private $sent;
    /** @ORM\Column(type="string") */
    private $error;
    /** @ORM\Column(type="string", length=255) */
    private $email_address;
    /** @ORM\Column(type="string") */
    private $semester;
    /** @ORM\Column(type="string") */
    private $course;
}
