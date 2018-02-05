# Google Photo Hop

This app shows you photos from your Google Photos Auto Backup album on this day from different years.

## Requirements

* PHP 7.1+
* [Composer](https://getcomposer.org)
* Node.js + Yarn
	* `npm install -g yarnpkg`

## Installation

1. Create OAuth Client ID and secret at [Google Console](https://console.developers.google.com/apis/)
2. `composer install`
3. Add your Client ID and secret to `.env`
4. `yarn install`
5. `yarn run encore dev` or `./node_modules/.bin/encore dev`
