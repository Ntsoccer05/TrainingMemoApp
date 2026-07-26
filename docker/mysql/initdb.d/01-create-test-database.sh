#!/bin/bash
set -e

# tests/CreatesApplication.php が接続先DBをtrainingmemo_testに固定しているため、
# 開発用DBとは別にテスト用DBを用意し、同じアプリユーザーに権限を付与しておく。
# (docker-entrypoint-initdb.d はデータディレクトリが空の初回起動時のみ実行される)
mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS trainingmemo_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON trainingmemo_test.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL
