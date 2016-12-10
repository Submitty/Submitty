#!/bin/bash

# Because it seems I'm always re-adding the same print statements, might as well just collect them
# into one file

target="/var/log/apache2"

let count=0
echo ${target}
for f in "${target}"/*
do
    sudo echo $(basename $f)
    sudo cat $f
    echo "~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~-~"
    let count=count+1
done
echo ""
echo "Count: $count"