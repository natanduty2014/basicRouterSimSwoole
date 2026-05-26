#!/bin/sh


# Obtém a data e hora atual no formato YYYY-MM-DD_HH-MM
current_datetime=$(date +"%Y-%m-%d_%H-%M")

# Create log directories that might be hidden by volume mounts
mkdir -p /var/log/supervisor
mkdir -p /var/log/php
mkdir -p /var/log/watch
chmod -R 777 /var/log

# Define o nome do arquivo de log com base na data e hora
log_file="/var/log/php/docker_log_$current_datetime.txt"


echo "executing watch on python"

python3 /public/watch.py &

echo "install composer dependencies"
cd /public/project/ && composer install && composer update && echo "start server" && php indexpro.php >> "$log_file" 2>&1
clear >> "$log_file"


echo "keep container running" && tail -f /dev/null
