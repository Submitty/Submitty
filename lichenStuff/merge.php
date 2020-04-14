<?php

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
    if (!in_array($sub, $this->user_matches)) {
      $this->user_matches[] = $sub;
    }
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

  public function __construct($uId, $vId) {
    $this->userId = $uId;
    $this->versionId = $vId;
  }

  public function getUid() {
    return $this->userId;
  }

  public function getVid() {
    return $this->versionId;
  }
}

function compareInterval($intervalOne, $intervalTwo) {
  return $intervalOne->getStart() > $intervalTwo->getStart();
}

/** Constructs an array of interval objects from matches
 *  @param filename - path to matches json
 *  @return list containing intervals
 */
function constructIntervals($filename) {
  $content = file_get_contents($filename);
  $arr = json_decode($content,true);
  $resultArray = [];
  foreach ($arr as $match) {
    $i = new \Interval($match['start'], $match['end']);
    foreach ($match['others'] as $o) {
      $s = new Submission($o['username'], $o['version']);
      $i->addUser($s);
    }
    $resultArray[] = $i;
  }
  usort($resultArray, "compareInterval");
  return $resultArray;
}

/**
 * Taken from: https://stackoverflow.com/questions/20210324/php-stack-implementation
 */
class Stack {

    protected $stack;
    protected $limit;

    public function __construct($limit = 10, $initial = array()) {
        // initialize the stack
        $this->stack = $initial;
        // stack can only contain this many items
        $this->limit = $limit;
    }

    public function push($item) {
        // trap for stack overflow
        if (count($this->stack) < $this->limit) {
            // prepend item to the start of the array
            array_unshift($this->stack, $item);
        } else {
            throw new RunTimeException('Stack is full!');
        }
    }

    public function pop() {
        if ($this->isEmpty()) {
            // trap for stack underflow
            throw new RunTimeException('Stack is empty!');
        } else {
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

function mergeIntervals($iArr) {
  $index = 0;
  $stack = new Stack();
  $stack->push($iArr[0]);
  ob_flush();
  ob_start();
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

  while(!$stack->isEmpty()) {
    $i = $stack->top();
    var_dump($i);
    $stack->pop();
  }
  file_put_contents("dump.txt", ob_get_flush());
}

$result = constructIntervals('matches.json');

mergeIntervals($result);

?>
