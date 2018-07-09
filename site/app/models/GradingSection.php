<?php

namespace app\models;

use app\libraries\Core;

/**
 * Represents a single section of students or teams, and their graders
 * Can be either registration or rotating
 *
 * @package app\models
 * @method Gradeable getGradeable()
 * @method string getName()
 * @method User[] getGraders()
 * @method User[] getUsers()
 * @method Team[] getTeams()
 */
class GradingSection extends AbstractModel {
    /**
     * @var Gradeable
     */
    private $gradeable;
    /**
     * If this is a registration section (false for rotating)
     * @var bool
     */
    private $registration;
    /**
     * @var string
     */
    private $name;
    /**
     * @var User[]
     */
    private $graders;
    /**
     * @var User[]
     */
    private $users;
    /**
     * @var Team[]
     */
    private $teams;

    public function __construct(Core $core, Gradeable $gradeable, bool $registration, string $name, $graders, $users, $teams) {
        parent::__construct($core);
        $this->gradeable = $gradeable;
        $this->registration = $registration;
        $this->name = $name;
        $this->graders = $graders;
        $this->users = $users;
        $this->teams = $teams;
    }

    public function containsUser(User $user) {
        if ($this->users === null) {
            //Team assignment
            return false;
        }

        foreach ($this->users as $section_user) {
            if ($section_user->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    public function containsTeam(Team $team) {
        if ($this->teams === null) {
            //Non-team assignment
            return false;
        }

        foreach ($this->teams as $section_team) {
            if ($section_team->getId() === $team->getId()) {
                return true;
            }
        }
        return false;
    }
}