#!/bin/bash

.github/ci/scripts/setup-pimcore-environment.sh

# Add Elasticsearch config if specified
if [ "$1" == "elasticsearch" ]; then
    CONFIG_FILE_PATH="config/packages/test/config.yaml"
    echo -e "\n# Added by functional test script\npimcore_generic_data_index:\n    index_service:\n        client_params:\n            client_type: 'elasticsearch'" >> "$CONFIG_FILE_PATH"
fi

cp .github/ci/files/composer.json .
cp bundles/pimcore/generic-data-index-bundle/codeception.dist.yml .