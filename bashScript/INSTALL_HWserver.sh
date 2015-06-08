#!/bin/bash


########################################################################
#
#    NOTE:  This is a first draft installation script
#
#    It is a copy and may not be fully in sync with latest version
#
########################################################################


# RSYNC NOTES
#  a = archive, recurse through directories, preserves file permissions, owner  [ NOT USED, DON'T WANT TO MESS W/ PERMISSIONS ]
#  r = recursive
#  v = verbose, what was actually copied
#  u = only copy things that have changed
#  z = compresses (faster for text, maybe not for binary)
#  (--delete, but probably dont want)
#  / trailing slash, copies contents into target
#  no slash, copies the directory & contents to target


BASEDIR=/fill/this/in
RCOS_REPO=/fill/this/in
PRIVATE_REPO=/fill/this/in

PHPUSER=fillthisin
CRONGROUP=fillthisin


########################################
# COPY THE SUBMISSION SERVER WEBSITE
rsync -rvuz $RCOS_REPO/public $PHPUSER@localhost:$BASEDIR/website



########################################
# COPY THE WEBSITE CUSTOMIZATIONS FOR THE SPECIFIC COURSES
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/template_before_https.php                         $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200_container.php
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/f14_csci1200_main.css                             $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci1200_main.css
rsync -vuz $RCOS_REPO/Sample_Files/sample_class/f14_csci1200_upload_message.php             $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200_upload_message.php

rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/template_before_https.php                         $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200test_container.php
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/f14_csci1200_main.css                             $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci1200test_main.css


rsync -vuz XXXXXXXXXXXXXXX/visualization/F14/template_before_https.php                      $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci4960_container.php
rsync -vuz XXXXXXXXXXXXXXX/visualization/F14/f14_csci4960_main.css                          $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci4960_main.css


########################################
# COPY THE CORE GRADING CODE

rsync -rvuz $RCOS_REPO/grading $BASEDIR/gradingcode
rsync -rvuz $RCOS_REPO/modules $BASEDIR/gradingcode


####
# DISALLOWED & WARNING KEYWORDS FROM SUBMITTED CODE
#rsync -rvuz $PRIVATE_REPO/disallowed_words.txt   $BASEDIR/courses/f14/csci1200/config/disallowed_words.txt
####



########################################
# COPY THE SCRIPT TO GRADE UPLOADED CODE
rsync -vuz  $RCOS_REPO/bashScript/grade_students.sh   $BASEDIR/bin/grade_students.sh
chgrp $CRONGROUP $BASEDIR/bin/grade_students.sh
chmod u+x $BASEDIR/bin/grade_students.sh
chmod g+x $BASEDIR/bin/grade_students.sh



################################################################################################################
################################################################################################################


./INSTALL_courses.sh


################################################################################################################
################################################################################################################

