<?php

namespace app\models;

use app\libraries\DatabaseUtils;

/**
 * Class Team
 */
class Team extends AbstractModel {
     
    /** @var string The id of this team of form "<unique number>_<creator user id>" */
    protected $id;
    /** @var array containing user ids of team members */
    protected $member_user_ids;
    /** @var array containing user ids of those invited to the team */
    protected $invited_user_ids;

    /**
     * Team constructor.
     * @param string $team_id
     */
    public function __construct($team_id) {
        $this->id = $team_id;
        $this->member_user_ids = array();
        $this->invited_user_ids = array();
    }

    /**
     * Get the id of the team
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Get user ids of team members
     * @return array(string)
    */
    public function getMembers() {
        return $this->member_user_ids;
    }

    /**
     * Get user ids of those invited to the team
     * @return array(string)
    */
    public function getInvitations() {
        return $this->invited_user_ids;
    }

    /**
     * Get number of users in team
     * @return integer
    */
    public function getSize() {
        return count($this->member_user_ids);
    }

    /**
     * Get whether or not a given user is on the team
     * @return bool
     */
    public function hasMember($user_id) {
        foreach($this->member_user_ids as $member) {
            if ($member === $user_id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get whether or not a given user is on the team
     * @return bool
     */
    public function sentInvite($user_id) {
        foreach($this->invited_user_ids as $invite) {
            if ($invite === $user_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add members and invited users from the teams table
     */
    public function addUsers($details) {
        foreach($details as $row) {
            if ($row['state'] === 1) {
                $this->member_user_ids[] = $row['user_id'];
            }
            else {
                $this->invited_user_ids[] = $row['user_id'];
            }
        }
    }
}