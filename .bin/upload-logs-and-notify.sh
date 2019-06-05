#!/bin/bash
# This script will send the notification if 'fail' parameter is passed it will
set -e # Exit with nonzero exit code if anything fails
export REPO_NAME='reports'
export REPO_LINK="https://github.com/wirecard/${REPO_NAME}"
export REPO_ADDRESS="${REPO_LINK}.git"

# add random sleep time to minimize conflict possibility
echo "Timestamp : $(date)"
RANDOM_VALUE=$[ ( RANDOM % 30 ) * $PRESTASHOP_RELEASE_VERSION + 1 ]
echo "Sleeping for: ${RANDOM_VALUE}"
sleep ${RANDOM_VALUE}s

#clone the repository where the screenshot should be uploaded
git clone ${REPO_ADDRESS}

echo $(pwd)
#create folder with current date
export TODAY=$(date +%Y-%m-%d)

export PRESTASHOP_CURRENT_VERSION=${PRESTASHOP_VERSION}
export PROJECT_FOLDER="prestashop-ee-${PRESTASHOP_CURRENT_VERSION}"
GATEWAY_FOLDER=${REPO_NAME}/${PROJECT_FOLDER}/${GATEWAY}
DATE_FOLDER=${GATEWAY_FOLDER}/${TODAY}

if [ ! -d "${GATEWAY_FOLDER}" ]; then
mkdir -p ${GATEWAY_FOLDER}
fi

if [ ! -d "${DATE_FOLDER}" ]; then
mkdir ${DATE_FOLDER}
fi

#copy report files
cp wirecardpaymentgateway/tests/_output/*.html ${DATE_FOLDER}
cp wirecardpaymentgateway/tests/_output/*.xml ${DATE_FOLDER}
if [[ $1 == 'fail' ]]; then
    cp wirecardpaymentgateway/tests/_output/*.fail.png ${DATE_FOLDER}
fi

cd ${REPO_NAME}
#push report files to the repository
git add ${PROJECT_FOLDER}/${GATEWAY}/${TODAY}/*
git commit -m "Add failed test screenshots from ${TRAVIS_BUILD_WEB_URL}"
git push -q https://${GITHUB_TOKEN}@github.com/wirecard/${REPO_NAME}.git master

#save commit hash
export SCREENSHOT_COMMIT_HASH=$(git rev-parse --verify HEAD)
if [[ $1 == 'fail' ]]; then
    cd ..
    #send slack notification
    bash .bin/send-notify.sh
fi
