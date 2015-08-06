<?php
namespace Psycle\MailOptions;

class PostInstall {
	public static function run() {
		copy(dirname(__DIR__) . '/psycle_mail.php', dirname(dirname(__DIR__)) . '/psycle_mail.php');
	}
}