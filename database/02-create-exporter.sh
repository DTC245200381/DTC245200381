#!/bin/sh
set -eu
# Dat ten mat khau exporter trong .env chi gom chu va so de tranh loi SQL.
mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY '${EXPORTER_PASSWORD}';
ALTER USER 'exporter'@'%' IDENTIFIED BY '${EXPORTER_PASSWORD}';
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
SQL
