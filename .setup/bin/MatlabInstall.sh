#!/bin/bash

###############################################################################################################################
# USER CONFIGURATION (Required)                                                                                  
# Please fill in the information below. 
#
# After getting access to your matlab installation from your institution, download them as a zip file.
# Unzip the file and move it somewhere safe. Then, enter that directory here.
# NOTE: This directory should hold the "install" executable.                                                                             
ABSOLUTE_PATH_TO_INSTALLATION_FILES=""
#
# The installation key for your files. To find this:
#     1) Login to the matlab website and select your license.
#     2) Navigate to the "Install and Activate" page. 
#     3) On the right beneath related tasks, select "Activate to retrieve license file." 
#     4) Next, select activate a computer, and follow the steps to activate your machine. 
#        NOTE: make sure that your computer login name matches the name of the user you want to have access to matlab. 
#         (eg. at RPI, you might use untrusted00) 
#     5) When this is done, you should be able to select "Get License File" from the activated computers list. 
#        Answer that the software is not installed and then continue. 
#     6) Download your license file and paste your file installation key below.
#        NOTE: regardless of how many activations you are going to do, you only need one installation key.
INSTALLATION_KEY=""
#
#     Please enter the paths to the licenses that you wish to activate. (obtained above, one per user.)
declare -a PATHS_TO_LICENSE_FILES=("") 
#
# End of user configuration                                                                                                   
###############################################################################################################################
# Pre-run checks.

if [ -z "$ABSOLUTE_PATH_TO_INSTALLATION_FILES" ]; then
  echo "ERROR: You did not provide any a path to the installation files."
  exit 1
fi


if [ -z "$INSTALLATION_KEY" ]; then
  echo "ERROR: You did not provide an installation key."
  exit 1
fi

iters=${#PATHS_TO_LICENSE_FILES[@]}
if [ "$iters" -eq "0" ]; then
  echo "ERROR: You did not provide any license files."
  exit 1
fi

if [ ! -d $ABSOLUTE_PATH_TO_INSTALLATION_FILES ]; then
  echo "ERROR: The directory" $ABSOLUTE_PATH_TO_INSTALLATION_FILES "does not exist."
  exit 1 
fi

#The random temporary folder we install from.
TMP_FOLDER=$(mktemp /tmp/matlab_install_XXXXXX -d)

if [ ! -d $TMP_FOLDER ]; then
  echo "ERROR: The temporary folder could not be generated."
  exit 1 
fi


#We provide a short circuit so we don't reinstall every time the script is run.
if [ -d "/usr/local/MATLAB/R2017a/" ]; then
  echo "Matlab is already installed, so we won't reinstall."
  echo "To reinstall, please delete your /usr/local/MATLAB directory."
else
  ###############################################################################################################################
  # Installation
  ###############################################################################################################################
  echo "Installing."
  if [ ! -e $ABSOLUTE_PATH_TO_INSTALLATION_FILES"/install" ]; then
    echo "ERROR: Could not find matlab executable."
    exit 1
  fi
  # An input file to ./install. Handles installation. Populated below.
  INPUT_FILE=$TMP_FOLDER/installer_input.txt
  # Move the necessary files into our temporary folder
  cp -R $ABSOLUTE_PATH_TO_INSTALLATION_FILES* $TMP_FOLDER
  # Remove the unnecessary input files (we're going to populate our own.)
  rm $INPUT_FILE

  #build the installer_input file.
  exec 3<> $INPUT_FILE
    echo "destinationFolder=/usr/local/MATLAB/R2017a/" >&3
    echo "fileInstallationKey="$INSTALLATION_KEY >&3
    echo "agreeToLicense=yes" >&3
    echo "outputFile=/tmp/mathworks_matlabLog.log" >&3
    echo "mode=silent" >&3
    # echo "activationPropertiesFile="$ACTIVATION_FILE >&3
    echo "product.MATLAB" >&3
  exec 3>&-

  # move into the temporary folder. 
  cd $TMP_FOLDER
  # run the installation script with the input file.
  sh ./install -inputFile $INPUT_FILE
fi #end of else.

###############################################################################################################################
# Activation
################################################################################################################################The activate_matlab script lives in Matlab's bin.
echo "Activating..."
# An input file to ./activate_matlab. Handles activation. Populated below.
ACTIVATION_FILE=$TMP_FOLDER/activate.ini
rm $ACTIVATION_FILE
#ACTIVATION STEP
iters=${#PATHS_TO_LICENSE_FILES[@]}
echo "Activating using" $iters "keys."
for ((i=0;i<$iters;i++)); do
  exec 3<> $ACTIVATION_FILE
    echo "isSilent=true" >&3
    echo "activateCommand=activateOffline" >&3
    echo "licenseFile="${PATHS_TO_LICENSE_FILES[i]} >&3
  exec 3>&-
  cd /usr/local/MATLAB/R2017a/bin
  if [ ! -e "/usr/local/MATLAB/R2017a/bin/activate_matlab.sh" ] ; then
    echo "ERROR: Could not find matlab activation executable."
    exit 1
  fi
  sh ./activate_matlab.sh -propertiesFile $ACTIVATION_FILE
done

#Now, remove the temp folder
rm -r $TMP_FOLDER

#The untrusted users need access to matlab.
echo "Updating permissions"
chmod -R o+rx /usr/local/MATLAB


#SYMLINK: EDIT THIS TO POINT TO THE APPROPRIATE VERSION
echo "Creating symlink."
cd /usr/local/bin/
ln -s /usr/local/MATLAB/R2017a/bin/matlab matlab

