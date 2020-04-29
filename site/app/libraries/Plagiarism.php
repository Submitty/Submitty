<?php

namespace app\libraries;

class Interval {

  private $start = 0;
  private $end = 0;
  private $user_matches = [];

  public function __construct($s, $e) {
    $this->start = $s;
    $this->end = $e;
  }

  public function getEnd() {
    return $this->end;
  }

  public function getStart() {
    return $this->start;
  }

  public function addUser($sub) {
    foreach($this->user_matches as $s) {
      if($sub->getUid() == $s->getUid() && $sub->getVid() == $s->getVid()) {
        
        return;
      }
    }
    $this->user_matches[] = $sub;
  }

  public function updateStart($newS) {
    $this->start = $newS;
  }

  public function updateEnd($newE) {
    $this->end = $newE;
  }

  public function getUsers() {
    return $this->user_matches;
  }

}

class Submission {
  private $userId;
  private $versionId;
  private $matchingPositions;

  public function __construct($uId, $vId, $pos) {
    $this->userId = $uId;
    $this->versionId = $vId;
    $this->matchingPositions = $pos;
  }

  public function getUid() {
    return $this->userId;
  }

  public function getVid() {
    return $this->versionId;
  }

  public function getMatchingPositions() {
    return $this->matchingPositions;
  }

}

/**
 * Taken from: https://stackoverflow.com/questions/20210324/php-stack-implementation
 */
class Stack {

    protected $stack;
    protected $limit;

    public function __construct($initial = array()) {
        // initialize the stack
        $this->stack = $initial;
    }

    public function push($item) {
        // trap for stack overflow
        array_unshift($this->stack, $item);
    }

    public function pop() {
        if (!$this->isEmpty()) {
            // pop item from the start of the array
            return array_shift($this->stack);
        }
    }

    public function top() {
        return current($this->stack);
    }

    public function isEmpty() {
        return empty($this->stack);
    }

}

class PlagiarismUtils {

  public static function compareInterval($intervalOne, $intervalTwo) {
    return $intervalOne->getStart() > $intervalTwo->getStart();
  }

  /** Constructs an array of interval objects from matches
   *  @param filename - path to matches json
   *  @return list containing intervals
   */
  public static function constructIntervals($filename) {
    $content = file_get_contents($filename);
    $arr = json_decode($content,true);
    $resultArray = [];
    foreach ($arr as $match) {
      $i = new Interval($match['start'], $match['end']);
      foreach ($match['others'] as $o) {
        $s = new Submission($o['username'], $o['version'], $o['matchingpositions']);
        $i->addUser($s);
      }
      $resultArray[] = $i;
    }
    usort($resultArray, "self::compareInterval");
    return $resultArray;
  }

  public static function mergeIntervals($iArr) {
    $stack = new Stack();
    $stack->push($iArr[0]);
    for ($i = 1; $i < count($iArr); $i++) {
      $cI = $stack->top();
      if ($cI->getEnd() < $iArr[$i]->getStart()) {
        $stack->push($iArr[$i]);
      } else if ($cI->getEnd() < $iArr[$i]->getEnd()) {
        $cI->updateEnd($iArr[$i]->getEnd());
        foreach ($iArr[$i]->getUsers() as $u) {
          $cI->addUser($u);
        }
        $stack->pop();
        $stack->push($cI);
      }
    }
    return $stack;
  }

}