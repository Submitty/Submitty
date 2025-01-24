<?php

namespace app\models;

use app\libraries\Core;
use app\models\gradeable\Submitter;

/**
 * Represents a single section of students or teams, and their graders
 * Can be either registration or rotating
 *
 * @package app\models
 * @method bool isRegistration()
 * @method string getName()
 * @method User[] getGraders()
 * @method User[] getUsers()
 * @method Team[] getTeams()
 */
class GradingSection extends AbstractModel {
    /**
     * If this is a registration section (false for rotating)
     * @prop
     * @var bool
     */
    protected $registration;
    /**
     * @prop
     * @var string|null
     */
    protected $name;
    /**
     * @prop
     * @var User[]
     */
    protected $graders;
    /**
     * @prop
     * @var User[]
     */
    protected $users;
    /**
     * @prop
     * @var Team[]
     */
    protected $teams;

    /**
     * @param Core $core
     * @param bool $registration
     * @param $name
     * @param array<User> $graders
     * @param array<User> $users
     * @param array<Team> $teams
     */
    public function __construct(Core $core, bool $registration, $name, array $graders, array $users, array $teams) {
        parent::__construct($core);
        $this->registration = $registration;
        $this->name = $name !== null ? (string) $name : null;
        $this->graders = $graders;
        $this->users = $users;
        $this->teams = $teams;
    }

    public function containsUser(User $user) {
        foreach ($this->users as $section_user) {
            /* @var User $section_user */
            if ($section_user->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    public function containsTeam(Team $team) {
        foreach ($this->teams as $section_team) {
            /* @var Team $section_team */
            if ($section_team->getId() === $team->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get an array of all Submitters for this section
     * @return Submitter[] All Submitters
     */
    public function getSubmitters() {
        if ($this->users !== []) {
            return array_map(function (User $user) {
                return new Submitter($this->core, $user);
            }, $this->users);
        }
        elseif ($this->teams !== []) {
            return array_map(function (Team $team) {
                return new Submitter($this->core, $team);
            }, $this->teams);
        }
        else {
            //No users, no teams, this section is empty!
            return [];
        }
    }
}
