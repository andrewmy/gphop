# Google Photo Hop

This app shows you photos from your Google Photos Auto Backup album on this day from different years.

## Requirements

* PHP 7.1+
* [Composer](https://getcomposer.org)
* Node.js + Yarn (`npm i -g yarn`)

## Installation

1. Create OAuth Client ID and secret at [Google Console](https://console.developers.google.com/apis/)
2. `composer install`
3. Add your Client ID and secret to `.env`
4. `yarn install`
5. `yarn run encore dev` or `./node_modules/.bin/encore dev`
	* `yarn run encore production` on prod

## Deployment with Deployer

1. Create a passwordless sudoer user
2. Install [Deployer](https://deployer.org) v4
3. Create a prod copy of the `.env` file named `.env.prod`
4. `cp deploy_servers.dist.yml deploy_servers.yml`
5. Enter your server credentials into `deploy_servers.yml`
6. `dep deploy:install` the first time, `dep deploy` when updating
