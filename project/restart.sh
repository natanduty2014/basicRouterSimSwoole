#!/bin/sh

# Obtém a data e hora atual no formato YYYY-MM-DD_HH-MM
current_datetime=$(date +"%Y-%m-%d_%H")

# Define o nome do arquivo de log com base na data e hora
log_file="/var/log/php/docker_log_restart_$current_datetime.txt"
chmod 777 "$log_file"
echo "restart server php" >> "$log_file"

# Mata todos os processos que estão usando a porta 9502
fuser -k 9502/tcp >> "$log_file" 2>&1

# Mata qualquer processo indexpro.php em execução
pkill -f indexpro.php >> "$log_file" 2>&1

sleep 0.5

cd /public/project && pkill -f indexpro.php >> "$log_file" 2>&1
echo "" >> "$log_file"
cd /public/project && php indexpro.php >> "$log_file" 2>&1 &
#permission on file all users txt
#quebrar linha
echo "" >> "$log_file"
# clear >> "$log_file"
# clear
echo "restart server php done" && exit 0