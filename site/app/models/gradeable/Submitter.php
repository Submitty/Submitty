<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;
use app\models\Team;
use app\models\User;

/**
 * Class Submitter
 * @package app\models\gradeable
 *
 * A trivial wrapper around a Team or a User instance so the GradedGradeable can
 *  have either and access it in a consistent way.
 */
class Submitter extends AbstractModel {

    /** @var Team|User The internal team or user instance */
    private $team_or_user;

    /**
     * Submitter constructor.
     * @param Core $core
     * @param Team|User $team_or_user The object for the submitter (team or user)
     */
    public function __construct(Core $core, $team_or_user) {
        parent::__construct($core);

        if($team_or_user === null) {
            throw new \InvalidArgumentException('Team or user must not be null');
        }

        if($team_or_user instanceof Team || $team_or_user instanceof User) {
            $this->team_or_user = $team_or_user;
        } else {
            throw new \InvalidArgumentException('Team or user must be a Team or a User');
        }
    }

    public function toArray() {
        return $this->team_or_user->toArray();
    }

    /**
     * Used to check if the submitter is a team
     * @return bool True if this submitter is a team
     */
    public function isTeam() {
        return $this->team_or_user instanceof Team;
    }

    /**
     * Gets the team/user object
     * @return Team|User The underlying team/user object
     */
    public function getObject() {
        return $this->team_or_user;
    }

    /**
     * Gets the user object if it is a user submitter
     * @return User|null The user object if it is a user submitter, otherwise null
     */
    public function getUser() {
        return !$this->isTeam() ? $this->team_or_user : null;
    }

    /**
     * Gets the team object if it is a team submitter
     * @return Team|null The team object if it is a team submitter, otherwise null
     */
    public function getTeam() {
        return $this->isTeam() ? $this->team_or_user : null;
    }

    /**
     * Gets the id of the user/team
     * @return string The user/team id of the submitter
     */
    public function getId() {
        return $this->team_or_user->getId();
    }

}