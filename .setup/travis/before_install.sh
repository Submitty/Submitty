#!/usr/bin/env bash

# this
BUILD_STAGE=$(echo ${TRAVIS_BUILD_STAGE_NAME} | tr '[:upper:]' '[:lower:]')
echo "before install"
