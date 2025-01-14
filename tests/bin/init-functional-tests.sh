#!/bin/bash

if [ -z "$1" ]
  then
    echo "No argument supplied. First argument is token for enterprise bundles."
    exit;
fi

SEARCH_ENGINE=${2:-openSearch}
CONFIG_FILE_PATH=".github/ci/files/config/packages/test/config.yaml"
if [ -z "$2" ]; then
    echo "Second argument was not supplied. Using OpenSearch for tests by default."
fi

docker compose down -v --remove-orphans

docker compose up -d

docker compose exec php .github/ci/scripts/setup-pimcore-environment-functional-tests.sh "$SEARCH_ENGINE"

docker compose exec php composer config --global --auth http-basic.repo.pimcore.com token $1

#docker compose exec php composer require pimcore/pimcore:10.0.0 --no-update
docker compose exec php composer update
#docker compose exec php composer update --prefer-lowest --prefer-stable

# Wait for Elasticsearch to be ready
step=5  # Check every 5 seconds
elapsed=0

if [ "$SEARCH_ENGINE" == "elasticsearch" ]; then
  echo "Waiting for Elasticsearch to be up..."
  until docker compose exec php curl -u elastic:somethingsecret -s http://elastic:9200/_cluster/health | grep -q '"status":"green"'; do
      echo "... ($elapsed seconds elapsed)"
      elapsed=$((elapsed + step))
      sleep $step
  done
  echo "Elasticsearch is up and ready."
else
  echo "Using OpenSearch for testing..."
fi

docker compose exec php vendor/bin/codecept run Functional -vv

printf "\n\n\n================== \n"
printf "Run 'docker compose exec php vendor/bin/codecept run Functional -vv' to re-run the tests.\n"
printf "Run 'docker compose down -v --remove-orphans' to shutdown container and cleanup.\n\n"