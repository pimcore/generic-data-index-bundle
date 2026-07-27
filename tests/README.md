# Test Environment
## Setup Test Environment
1. Spin up your docker container:
    ```bash
    docker-compose up -d
    ```
2. Open the bash of the php container:
    ```bash
    docker-compose exec php bash
    ```
3. Move to the working directory:
    ```bash
    cd /var/cli
    ```
4. Install the dependencies:
    ```bash
    composer install
    ```

## Run the tests
When all dependencies are installed you can run the tests with the following command:
```bash
./vendor/bin/codecept run -vvv
```
## Writing functional tests

Never use bare numeric literals as element keys, filenames or search terms in
functional tests. The full text search matches all index fields including the
element id, and auto-increment ids collide with such literals depending on how
many elements previous tests created (the suite runs with `shuffle: true`).
See https://github.com/pimcore/generic-data-index-bundle/issues/462.
