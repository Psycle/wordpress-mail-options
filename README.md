# WordPress Mail Options Plugin

[![Build Status](https://travis-ci.org/Psycle/wordpress-plugin-mail-options.svg)](https://travis-ci.org/Psycle/wordpress-plugin-mail-options)

## About

This plugin hooks into the wp_mail() function and overrides the From address.

This is required for sites that send email using external mail services which require a correct 'From' address for authentication.

Any existing 'From' address will be moved to the 'Sender' header. If no 'Reply-To' header is set then the original 'From' header is used.

## Usage

This plugin is available via Composer/Packagist (using semantic versioning), so just run the following composer command

composer require psycle-wordpress-plugins/mail-options

## New releases

New release tags pushed to the gitlab repository (https://github.com/Psycle/wordpress-plugin-mail-option) will automatically be pushed to the packagist.org composer repository. This is achieved using a built in github hook.
