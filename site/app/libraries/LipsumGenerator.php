<?php

namespace app\libraries;

/**
 * Class LipsumGenerator
 *
 * Provides a way to generate dummy text to be used on a page using
 * http://www.lipsum.com as the generator. More convinent than
 * having to go to the site itself and copy/paste and then
 * manually converting all line breaks into <br /> tags in the HTML!
 */
class LipsumGenerator {
    const BASE_URL = "http://www.lipsum.com/feed/json";

    private function __construct() {}
    private function __clone() {}

    /**
     * Generate a given amount of paragraphs of dummy text
     *
     * @param int  $amount Number of paragraphs to generate. Minimum value is 1.
     * @param bool $start  Should the first paragraph start with "Lorem ipsum dolor sit amet..."
     *
     * @return string
     */
    public static function getParagraphs($amount = 5, $start = true) {
        return nl2br(static::sendRequest('paras', intval($amount), $start === true));
    }

    /**
     * Generate a given amount of words of dummy text
     *
     * @param int  $amount Number of wrods to generated. Minimum value is 5.
     * @param bool $start  Should the first paragraph start with "Lorem ipsum dolor sit amet..."
     *
     * @return string
     */
    public static function getWords($amount = 5, $start = true) {
        return nl2br(static::sendRequest('words', intval($amount), $start === true));
    }

    /**
     * Generate a given amount of bytes of dummy text
     *
     * @param int  $amount Number of bytes of words to generate. Minimum value is 27.
     * @param bool $start  Should the returned bytes start with "Lorem ipsum dolor sit amet..."
     *
     * @return string
     */
    public static function getBytes($amount = 27, $start = true) {
        return nl2br(static::sendRequest('bytes', intval($amount), $start === true));
    }

    /**
     * Fetch lists from http://www.lipsum.com which we then parse down into a nested array
     * where the array points to array that contains each individual line from the response such
     * that it best matches what you would get if you were to go to the site itself. For example,
     * if you wanted 5 lists, it would give you 5 groupings of some number of sentences (which
     * are separated).
     *
     * @param int  $amount Number of lists to generate. Minimum value is 1.
     * @param bool $start  Should the first line start with "Lorem ipsum dolor sit amet..."
     *
     * @return array
     */
    public static function getLists($amount = 5, $start = true) {
        $return = static::sendRequest('lists', intval($amount), $start === true);
        $return = explode("\n", $return);
        foreach ($return as $key => $value) {
            $value = explode(".", trim($value));
            unset($value[count($value)-1]);
            foreach ($value as $kkey => $vvalue) {
                $value[$kkey] = trim($vvalue).".";
            }
            $return[$key] = $value;
        }
        return $return;
    }

    /**
     * Sends a request to http://www.lipsum.com for its JSON feed for the specified
     * type of response (paragraphs, words, etc.) of the desired amount and whether
     * or not the response should start with "Lorem ipsum dolor sit amet..."
     *
     * @param string $type
     * @param int    $amount
     * @param bool   $start
     *
     * @return string
     */
    private static function sendRequest($type, $amount, $start) {
        $start = ($start === true) ? "yes" : "no";
        $url = static::BASE_URL.'?'.http_build_query(array("what" => $type, "amount" => $amount, "start" => $start));
        $ch = curl_init($url);
        $timeout = 5;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($data, true);
        return $json['feed']['lipsum'];
    }
}