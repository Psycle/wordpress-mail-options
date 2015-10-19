php --version;
export PLUGIN_DIR=$(pwd);
export PLUGIN_SLUG=$(basename $(pwd));
git clone https://github.com/tierra/wordpress.git /tmp/wordpress;
# git clone . "/tmp/wordpress/src/wp-content/plugins/$PLUGIN_SLUG";
cd /tmp/wordpress;
git checkout $WP_VERSION;
mysql -X -e "DROP DATABASE IF EXISTS wordpress_tests;" -uroot -proot;
mysql -X -e "CREATE DATABASE wordpress_tests;" -uroot -proot;
cp wp-tests-config-sample.php wp-tests-config.php;
sed -i bak "s/youremptytestdbnamehere/wordpress_tests/" wp-tests-config.php;
sed -i bak "s/yourusernamehere/root/" wp-tests-config.php;
sed -i bak "s/yourpasswordhere/root/" wp-tests-config.php;
sed -i bak "s/localhost/127\.0\.0\.1/" wp-tests-config.php;
cd $PLUGIN_DIR/tests;

phpunit;

