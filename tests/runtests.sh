# REQUIREMENTS:
# PHP_CodeSniffer (pyrus install pear/PHP_CodeSniffer)


php --version;
export PLUGIN_SLUG=$(basename $(pwd))
git clone https://github.com/tierra/wordpress.git /tmp/wordpress
cd ..
rm -fr "/tmp/wordpress/src/wp-content/plugins/$PLUGIN_SLUG";
cp -R $PLUGIN_SLUG "/tmp/wordpress/src/wp-content/plugins/$PLUGIN_SLUG"
cd /tmp/wordpress
git checkout $WP_VERSION
mysql -X -e "DROP DATABASE IF EXISTS wordpress_unit_tests;" -uroot -proot
mysql -X -e "CREATE DATABASE wordpress_unit_tests;" -uroot -proot
cp wp-tests-config-sample.php wp-tests-config.php
sed -i bak "s/youremptytestdbnamehere/wordpress_unit_tests/" wp-tests-config.php
sed -i bak "s/yourusernamehere/travis/" wp-tests-config.php
sed -i bak "s/yourpasswordhere//" wp-tests-config.php

mkdir /tmp/wpcs
cd /tmp/wpcs
git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git .
cd "/tmp/wordpress/src/wp-content/plugins/$PLUGIN_SLUG/tests"
phpcs --config-set installed_paths /tmp/wpcs

phpunit
cd ../
phpcs --standard=WordPress --colors -d error_reporting=0 --extensions=php -n .
