# syntheticsidecar
Wordpress Sidecar for Synthetic Journalist

# requires composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# install prerequsites
composer i --no-dev --no-interaction

# configure rabbit mq
vi config.php

# run worker
./worker.sh &
