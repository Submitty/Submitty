<?php

class QueueHistorySort{
    public $name;
    public $queue;
}

function compareByName($a,$b){
    return strcmp($a -> name, $b -> name);
}

function sortTable($entries,$column){
    if($column == "name"){
        usort($entries,"compareByName");
    }
}

$columnToSort = "name";
sortTable($entries,$columnToSort);
