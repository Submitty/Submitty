<?php

namespace app\libraries;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class FileUtils
 *
 * Contains various useful functions for the polling system.
 */
class PollUtils {
    /**
     * Generates a PHP array containing the all the poll data used in the export procedure
     * @param array $polls the array of polls to be summarized
     */
    public static function getPollExportData(array $polls): array {
        $data = [];
        foreach ($polls as $poll) {
            $poll_array = [];
            $poll_array["name"] = $poll->getName();
            $poll_array["question"] = $poll->getQuestion();
            $responses = [];
            foreach ($poll->getResponses() as $response) {
                $responses[$response] = $poll->getResponseString($response);
            }
            $poll_array["responses"] = $responses;
            $poll_array["correct_responses"] = $poll->getAnswers();
            $poll_array["release_date"] = $poll->getReleaseDate();
            $data[$poll->getID()] = $poll_array;
        }
        return $data;
    }
}
