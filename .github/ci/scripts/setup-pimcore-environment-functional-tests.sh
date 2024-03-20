#!/bin/bash

.github/ci/scripts/setup-pimcore-environment.sh

cp .github/ci/files/composer.json .
cp bundles/pimcore/generic-data-index-bundle/codeception.dist.yml .