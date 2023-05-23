#!/bin/bash

while true; do
    echo "Waiting for MQ"
    php ./worker.php
    sleep 2
done