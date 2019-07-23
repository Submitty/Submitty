<?php

namespace app\models;

use app\libraries\Core;

class SimpleGradeOverridenUser extends AbstractModel {

    /** @property @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @property @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @property @var string The first name of the user */
    protected $legal_first_name;
    /** @property @var string The preferred first name of the user if exists */
    protected $preferred_first_name = "";
    /** @property @var  string The name to be displayed by the system (either preferred last name or legal first name) */
    protected $displayed_first_name;
    /** @property @var string The last name of the user */
    protected $legal_last_name;
    /** @property @var string The preferred last name of the user if exists */
    protected $preferred_last_name;
    /** @property @var  string The name to be displayed by the system (either preferred last name or legal last name) */
    protected $displayed_last_name;
    /** @property @var  integer The overrided marks */
    protected $marks;
    /** @property @var  string The comment to be displayed */
    protected $comment;
    
    /**
     * User constructor.
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }

        $this->loaded = true;
        $this->id = $details['user_id'];
        $this->legal_first_name = $details['user_firstname'];
        if (isset($details['user_preferred_firstname']) && $details['user_preferred_firstname'] !== "") {
            $this->preferred_first_name = $details['user_preferred_firstname'];
            $this->displayed_first_name = $details['user_preferred_firstname'];
        }
        else{
            $this->displayed_first_name = $details['user_firstname'];
        }

        $this->legal_last_name = $details['user_lastname'];
        if (isset($details['user_preferred_lastname']) && $details['user_preferred_lastname'] !== "") {
            $this->preferred_last_name = $details['user_preferred_lastname'];
            $this->displayed_last_name = $details['user_preferred_lastname'];
        }
        else{
            $this->displayed_last_name = $details['user_lastname'];
        }

        if(isset($details['marks'])){
            $this->marks = $details['marks'];
        }

        if(isset($details['comment'])){
            $this->comment = $details['comment'];
        }
    }

}
