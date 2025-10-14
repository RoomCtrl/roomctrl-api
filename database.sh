#!/bin/sh
php bin/console doctrine:database:drop --force
rm -f migrations/*.php
php bin/console doctrine:database:create
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load