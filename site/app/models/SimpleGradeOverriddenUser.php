<?php

namespace app\models;

use app\libraries\Core;

/**
 * @method string getId()
 * @method string getDisplayedGivenName()
 * @method string getDisplayedFamilyName()
 * @method integer getMarks()
 * @method string getComment()
 */
class SimpleGradeOverriddenUser extends AbstractModel {
    /** @prop
     * @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @prop
     * @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @prop
     * @var string The given name of the user */
    protected $legal_given_name;
    /** @prop
     * @var string The preferred given name of the user if exists */
    protected $preferred_given_name;
    /** @prop
     * @var  string The name to be displayed by the system (either preferred given name or legal given name) */
    protected $displayed_given_name;
    /** @prop
     * @var string The family name of the user */
    protected $legal_family_name;
    /** @prop
     * @var string The preferred family name of the user if exists */
    protected $preferred_family_name;
    /** @prop
     * @var  string The name to be displayed by the system (either preferred family name or legal family name) */
    protected $displayed_family_name;
    /** @prop
     * @var  integer The overridden marks */
    protected $marks;
    /** @prop
     * @var  string The comment to be displayed */
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
        $this->legal_given_name = $details['user_givenname'];
        if (isset($details['user_preferred_givenname'])) {
            $this->preferred_given_name = $details['user_preferred_givenname'];
            $this->displayed_given_name = $details['user_preferred_givenname'];
        }
        else {
            $this->displayed_given_name = $details['user_givenname'];
        }

        $this->legal_family_name = $details['user_familyname'];
        if (isset($details['user_preferred_familyname'])) {
            $this->preferred_family_name = $details['user_preferred_familyname'];
            $this->displayed_family_name = $details['user_preferred_familyname'];
        }
        else {
            $this->displayed_family_name = $details['user_familyname'];
        }

        if (isset($details['marks'])) {
            $this->marks = $details['marks'];
        }

        if (isset($details['comment'])) {
            $this->comment = $details['comment'];
        }
    }
}
