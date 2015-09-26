#!/bin/bash

echo "Starting selenium"
nohup bash -c "java -jar \"$SELENIUM_JAR\" 2>&1 &"
sleep 5

wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$SELENIUM_HUB_URL/wd/hub/status" -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started"
else
    echo "Finished setup"
fi