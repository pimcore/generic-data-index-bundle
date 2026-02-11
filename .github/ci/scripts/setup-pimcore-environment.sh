#!/bin/bash

set -eu

mkdir -p var/config
mkdir -p bin

cp -r .github/ci/files/config/. config
cp -r .github/ci/files/templates/. templates
cp -r .github/ci/files/bin/console bin/console
chmod 755 bin/console
cp -r .github/ci/files/kernel/. kernel
cp -r .github/ci/files/public/. public

# Detect Elasticsearch: if the ES host is reachable, switch client_type to elasticsearch
if [ -n "${PIMCORE_ELASTIC_SEARCH_HOST:-}" ]; then
    if curl -s --max-time 5 "http://${PIMCORE_ELASTIC_SEARCH_HOST}" > /dev/null 2>&1; then
        echo "Elasticsearch detected at ${PIMCORE_ELASTIC_SEARCH_HOST}, switching client_type to elasticsearch"
        cat >> config/packages/test/config.yaml <<'ESCONFIG'

# Elasticsearch client type (auto-detected by setup script)
pimcore_generic_data_index:
    index_service:
        client_params:
            client_type: 'elasticsearch'
ESCONFIG
    fi
fi