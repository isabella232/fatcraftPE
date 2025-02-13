#!/usr/bin/env bash


# for mysql docker container
MYSQL_ROOT_PASS="root"

# for building docker images
MYSQL_HOST="localhost"
MYSQL_PORT=3306
MYSQL_USER="root"
MYSQL_PASS="root"
MYSQL_DATA="database"

# external IP (auto) OR local IP
SERVER_IP=""

updateConfig() {
    sed -i 's/<<MYSQL_USER>>/'$MYSQL_USER'/g' $1
    sed -i 's/<<MYSQL_PASS>>/'$MYSQL_PASS'/g' $1
    sed -i 's/<<MYSQL_DATA>>/'$MYSQL_DATA'/g' $1
    sed -i 's/<<MYSQL_HOST>>/'$MYSQL_HOST'/g' $1
    sed -i 's/<<MYSQL_PORT>>/'$MYSQL_PORT'/g' $1

    sed -i 's/<<SERVER_IP>>/'$SERVER_IP'/g' $1

    echo "file $1 builded!"
}
