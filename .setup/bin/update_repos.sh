#!/usr/bin/env bash

# Submitty source code is split among a number of repositories.

# This file handles initial checkout and/or updates the repositories
# to ensure all of the source code is up-to-date.

########################################################################

# These variables specify the minimum version necessary for
# dependencies between versions.

lichen_repo_dir=${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/Lichen
min_lichen_version=v.18.06.00

# If a clone/directory of the repository does not yet exist, we will do
# a shallow clone (depth=1, single commit) of the repository for the
# specified version.

git clone --branch ${min_lichen_version} --depth 1 https://example.com/my/repo.git


# If the clone/directory of the repository exists and matches the
# specified minimum version/tag --OR-- the commit with the specified
# version/tag is in the history of the current commit, we do nothing.




git rev-parse --is-shallow-repository



pushd ${lichen_repo_dir} > /dev/null
git merge-base --is-ancestor "${min_lichen_version}" HEAD
if [ $? -ne 0 ]; then
    git status
    git log
    echo -e "ERROR: Submitty/Lichen repository history does not contain version ${min_lichen_version}"
    echo -e "   Run 'git fetch' to get the tags from github."
    echo -e "   Also check to be sure your current branch is up-to-date."
    exit 1
fi
popd > /dev/null





import os

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/Tutorial"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/Tutorial")
    os.system("git pull origin master")

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/AnalysisTools"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/AnalysisTools")
    os.system("git pull origin master")

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/Lichen"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/Lichen")
    os.system("git pull origin master")
