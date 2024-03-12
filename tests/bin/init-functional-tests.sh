#!/bin/bash

if [ -z "$1" ]
  then
    echo "No argument supplied. First and only argument is token for enterprise bundles."
    exit;
fi

docker-compose down -v --remove-orphans

docker-compose up -d

docker-compose exec php .github/ci/scripts/setup-pimcore-environment-functional-tests.sh

docker-compose exec php composer config --global --auth http-basic.enterprise.repo.pimcore.com token $1

#docker-compose exec php composer require pimcore/pimcore:10.0.0 --no-update
docker-compose exec php composer update
#docker-compose exec php composer update --prefer-lowest --prefer-stable

docker-compose exec php vendor/bin/codecept run Functional -vv

printf "\n\n\n================== \n"
printf "Run 'docker-compose exec php vendor/bin/codecept run Functional -vv' to re-run the tests.\n"
printf "Run 'docker-compose down -v --remove-orphans' to shutdown container and cleanup.\n\n"