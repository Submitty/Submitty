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
            $responses = [];
            foreach ($poll->getResponses() as $response) {
                $responses[$response] = $poll->getResponseString($response);
            }
            $data[] = [
                "id" => $poll->getID(),
                "name" => $poll->getName(),
                "question" => $poll->getQuestion(),
                "responses" => $responses,
                "correct_responses" => $poll->getAnswers(),
                "release_date" => $poll->getReleaseDate(),
                "status" => $poll->getStatus()
            ];
        }
        return $data;
    }
}
