# REQUIREMENTS:
# PHP_CodeSniffer (pyrus install pear/PHP_CodeSniffer)


php --version;
export BASE_DIR=$(pwd)
export WP_TEST_DIR=/tmp/wordpress;
export PLUGIN_SLUG=$(basename $(pwd))
export WP_VERSIONS=("4.3.1" "4.3")
export WORDPRESS_SVN="https://develop.svn.wordpress.org";
export WORDPRESS_SVN_TRUNK="https://develop.svn.wordpress.org/trunk";
export WORDPRESS_SVN_TAGS="https://develop.svn.wordpress.org/tags/";
export WORDPRESS_SVN_BRANCHES="https://develop.svn.wordpress.org/branches/";
export WORDPRESS_DB_NAME=wptests;
export WORDPRESS_DB_USER=root;
export WORDPRESS_DB_PASS=root;


if [ ! -f /tmp/composer.phar ]; then
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
fi

php /tmp/composer.phar global require "phpunit/phpunit=5.0.*"



if [ ! -d "/tmp/wpcs" ]; then
    echo "Installing wordpress coding standards";
    mkdir /tmp/wpcs
    cd /tmp/wpcs
    git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git .
    phpcs --config-set installed_paths /tmp/wpcs
fi

for i in ${WP_VERSIONS[@]}; do
    export CURRENT_WP_TAG_DIR=${WP_TEST_DIR}_${i}
    if [ ! -d "${CURRENT_WP_TAG_DIR}" ]; then
        echo "Checking out wordpress tag ${i}";
        svn co "${WORDPRESS_SVN_TAGS}${i}"  ${CURRENT_WP_TAG_DIR}
    else
        svn up ${CURRENT_WP_TAG_DIR};
        echo "Updating wordpress tag ${i}";
    fi
    cd ${CURRENT_WP_TAG_DIR}

    echo "Copying plugin into wordpress installation."
    rm -fr "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG";
    cp -R $BASE_DIR "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG"

    echo "Setting up database '${WORDPRESS_DB_NAME}'"
    mysql -Xv -e "DROP DATABASE IF EXISTS $WORDPRESS_DB_NAME;" -u $WORDPRESS_DB_USER -p$WORDPRESS_DB_PASS;
    mysql -Xv -e "CREATE DATABASE $WORDPRESS_DB_NAME;" -u $WORDPRESS_DB_USER -p$WORDPRESS_DB_PASS;

    echo "Setting up database access";
    cp wp-tests-config-sample.php wp-tests-config.php
    sed -i bak "s/youremptytestdbnamehere/${WORDPRESS_DB_NAME}/" wp-tests-config.php
    sed -i bak "s/yourusernamehere/root/" wp-tests-config.php
    sed -i bak "s/yourpasswordhere/root/" wp-tests-config.php
    sed -i bak "s/localhost/127.0.0.1/" wp-tests-config.php


    echo "Running tests."
    cd "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/${PLUGIN_SLUG}/tests"

    ~/.composer/vendor/bin/phpunit --version
    ~/.composer/vendor/bin/phpunit -v

    echo "Finished running tests."
    rm -fr "${CURRENT_WP_TAG_DIR}/src/wp-content/plugins/$PLUGIN_SLUG";
    echo "Removed plugin directory from wordpress plugins test folder"
done

cd $BASE_DIR
phpcs -v --standard=WordPress --colors -d error_reporting=0 --extensions=php -n .
exit 0;
