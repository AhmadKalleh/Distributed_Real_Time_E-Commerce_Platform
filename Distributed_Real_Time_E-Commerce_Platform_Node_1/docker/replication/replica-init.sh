#!/bin/bash
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CHANGE REPLICATION SOURCE TO
        SOURCE_HOST='mysql-primary',
        SOURCE_PORT=3306,
        SOURCE_USER='replicator',
        SOURCE_PASSWORD='${MYSQL_REPLICATION_PASSWORD}',
        SOURCE_AUTO_POSITION=1,
        GET_SOURCE_PUBLIC_KEY=1;
    START REPLICA;
    SET GLOBAL read_only = ON;
    SET GLOBAL super_read_only = ON;
EOSQL