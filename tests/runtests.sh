#!/bin/bash

function display_title {
    title="| ${1} |"
    size=${#title}
    printf %${size}s |tr " " "="
    echo " "
    echo $title
    printf %${size}s |tr " " "="
    echo " "
}

clear

export BASE_DIR=$(pwd)
export WP_TEST_DIR=/tmp/wordpress/wordpress;
export PLUGIN_SLUG=$(basename $(pwd))

export TEST_BINARIES_DIRECTORY="/tmp/unit_tests/"
export COMPOSER_PACKAGE_DIR="${TEST_BINARIES_DIRECTORY}"
export PHPUNIT_BINARY="${COMPOSER_PACKAGE_DIR}/vendor/bin/phpunit"
export PHPCS_BINARY="${COMPOSER_PACKAGE_DIR}/vendor/bin/phpcs"
export COMPOSER_PHAR=${TEST_BINARIES_DIRECTORY}/composer.phar
export WORDPRESS_SVN="https://develop.svn.wordpress.org";
export WORDPRESS_SVN_TRUNK="https://develop.svn.wordpress.org/trunk";
export WORDPRESS_SVN_TAGS="https://develop.svn.wordpress.org/tags/";
export WORDPRESS_SVN_BRANCHES="https://develop.svn.wordpress.org/branches/";

if [ -z $CI_BUILD_ID ]; then
    export CI_BUILD_ID=localbuild
fi

if [ -z $WORDPRESS_TESTS_VERSIONS ]; then
    if [ -z $WORDPRESS_TESTS_VERSION ]; then
        display_title "Please specify a WordPress version number in the WORDPRESS_TESTS_VERSION environment variable"
        exit 1
    else
    export WORDPRESS_TESTS_VERSIONS=("${WORDPRESS_TESTS_VERSION}")
    fi
fi

if [ -z $WORDPRESS_TESTS_DB_HOST ]; then
    export WORDPRESS_TESTS_DB_HOST=127.0.0.1
fi

if [ -z $WORDPRESS_TESTS_DB_NAME ]; then
    export WORDPRESS_TESTS_DB_NAME=wordpress_unit_tests
fi

if [ -z $WORDPRESS_TESTS_DB_USER ]; then
    export WORDPRESS_TESTS_DB_USER=root
fi

if [ -z $WORDPRESS_TESTS_DB_PASS ]; then
    export WORDPRESS_TESTS_DB_PASS=root
fi

exitcode=0;
php --version;

# Create the directory for out test files.
if [ ! -d "${TEST_BINARIES_DIRECTORY}" ]; then
    mkdir ${TEST_BINARIES_DIRECTORY};
fi

# Set up composer phar, update if it already exists
if [ ! -f "${TEST_BINARIES_DIRECTORY}/composer.phar" ]; then
    cd ${TEST_BINARIES_DIRECTORY}
    curl -sS https://getcomposer.org/installer | php
else
    php ${COMPOSER_PHAR} self-update
fi

# Create the directory for our composer packages.
if [ ! -d "${COMPOSER_PACKAGE_DIR}" ]; then
    mkdir -p ${COMPOSER_PACKAGE_DIR}
fi

cp ${BASE_DIR}/tests/test_composer.json ${COMPOSER_PACKAGE_DIR}/composer.json
cd ${COMPOSER_PACKAGE_DIR}
php ${COMPOSER_PHAR} update
${PHPCS_BINARY} --config-set installed_paths ${COMPOSER_PACKAGE_DIR}vendor/wp-coding-standards/wpcs

for i in ${WORDPRESS_TESTS_VERSIONS[@]}; do

    export WORDPRESS_TESTS_CURRENT_DB_NAME="${WORDPRESS_TESTS_DB_NAME}_${CI_BUILD_ID}"
    display_title "Testing plugin on WordPress v${i}"

    export CURRENT_WP_TAG_DIR=${WP_TEST_DIR}_${i}
    export WP_TESTS_DIR="${CURRENT_WP_TAG_DIR}/tests/phpunit"
    echo "Checking ${CURRENT_WP_TAG_DIR} for wordpress"
    if [ ! -d "${CURRENT_WP_TAG_DIR}" ]; then
        echo "Checking out wordpress tag ${i}";
        mkdir -p ${CURRENT_WP_TAG_DIR}
        cd ${CURRENT_WP_TAG_DIR}
        svn co "${WORDPRESS_SVN_TAGS}${i}" .
    else
        echo "Found WordPress v${i}"
    fi

    cd ${CURRENT_WP_TAG_DIR}

    echo "Copying plugin into wordpress installation."
    rm -fr "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG";
    cp -R $BASE_DIR "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG"

    echo "Setting up database '${WORDPRESS_TESTS_CURRENT_DB_NAME}'"
    mysql -Xv -e "DROP DATABASE IF EXISTS $WORDPRESS_TESTS_CURRENT_DB_NAME;" -h ${WORDPRESS_TESTS_DB_HOST} -u$WORDPRESS_TESTS_DB_USER -p$WORDPRESS_TESTS_DB_PASS;
    if [ $? != 0 ]; then
        display_title "Failed to drop database. Test script failed."
        exit 1;
    fi
    mysql -Xv -e "CREATE DATABASE $WORDPRESS_TESTS_CURRENT_DB_NAME;" -h ${WORDPRESS_TESTS_DB_HOST} -u $WORDPRESS_TESTS_DB_USER -p$WORDPRESS_TESTS_DB_PASS;
    if [ $? != 0 ]; then
        display_title "Failed to create database. Test script failed."
        exit 1;
    fi

    # Set up the WordPress database config.
    echo "Setting up database access";
    cp wp-tests-config-sample.php wp-tests-config.php
    sed -i bak "s/youremptytestdbnamehere/${WORDPRESS_TESTS_CURRENT_DB_NAME}/" wp-tests-config.php
    sed -i bak "s/yourusernamehere/${WORDPRESS_TESTS_DB_USER}/" wp-tests-config.php
    sed -i bak "s/yourpasswordhere/${WORDPRESS_TESTS_DB_PASS}/" wp-tests-config.php
    sed -i bak "s/localhost/${WORDPRESS_TESTS_DB_HOST}/" wp-tests-config.php

    # Run the unit tests
    echo "Running tests."
    cd "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/${PLUGIN_SLUG}/tests"

    ${PHPUNIT_BINARY} --version
    ${PHPUNIT_BINARY} -v

    if [ $? != 0 ]; then
        exitcode=1
    fi

    echo "Finished running tests."
    rm -fr "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG";
    echo "Removed plugin directory from wordpress plugins test folder"

    display_title "End of testing plugin on WordPress v${i}"

    # Drop the test database
    mysql -Xv -e "DROP DATABASE IF EXISTS $WORDPRESS_TESTS_CURRENT_DB_NAME;" -h ${WORDPRESS_TESTS_DB_HOST} -u$WORDPRESS_TESTS_DB_USER -p$WORDPRESS_TESTS_DB_PASS;
done

display_title "Running phpcs on ${PLUGIN_SLUG}"

cd $BASE_DIR
${PHPCS_BINARY} --ignore=./vendor -v --standard=WordPress --colors -d error_reporting=0 --extensions=php -n .

if [ $? != 0 ]; then
exitcode=1
fi


exit $exitcode;
