#!/bin/bash

for i in $(ps -ef | grep submitty_autograding_shipper | grep -v grep | awk '{print $2}'); do echo "Kill shipper pid $i"; kill $i || true; done
for i in $(ps -ef | grep submitty_autograding_worker | grep -v grep | awk '{print $2}'); do echo "Kill worker pid $i"; kill $i || true; done

