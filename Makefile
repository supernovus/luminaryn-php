.PHONY: help pull build push all
.PHONY: build-core build-mongo build-mysql build-pgsql
.PHONY: build-v8 build-mongo-v8 build-mysql-v8 build-pgsql-v8
.PHONY: build-dbs build-dbs-v8
.PHONY: push-core push-mongo push-mysql push-pgsql
.PHONY: push-v8 push-mongo-v8 push-mysql-v8 push-pgsql-v8
.PHONY: push-dbs push-dbs-v8
.DEFAULT: help

help:
	less Makefile

pull:
	sudo docker pull php:7-fpm

build-core: pull
	sudo docker build -f Dockerfile.core -t luminaryn/php:7-core .

build-v8:
	sudo docker build -f Dockerfile.v8 -t luminaryn/php:7-v8js .

build-mongo:
	sudo docker build -f Dockerfile.mongo -t luminaryn/php:7-mongo .

build-mysql:
	sudo docker build -f Dockerfile.mysql -t luminaryn/php:7-mysql .

build-pgsql:
	sudo docker build -f Dockerfile.pgsql -t luminaryn/php:7-pgsql .

build-mongo-v8:
	sudo docker build -f Dockerfile.mongo-v8 -t luminaryn/php:7-v8js-mongo .

build-mysql-v8:
	sudo docker build -f Dockerfile.mysql-v8 -t luminaryn/php:7-v8js-mysql .

build-pgsql-v8:
	sudo docker build -f Dockerfile.pgsql-v8 -t luminaryn/php:7-v8js-pgsql .

build-dbs: build-mongo build-mysql build-pgsql

build-dbs-v8: build-mongo-v8 build-mysql-v8 build-pgsql-v8

build: build-core build-dbs 

push-core:
	sudo docker push luminaryn/php:7-core

push-v8:
	sudo docker push luminaryn/php:7-v8js

push-mongo:
	sudo docker push luminaryn/php:7-mongo

push-mysql:
	sudo docker push luminaryn/php:7-mysql

push-pgsql:
	sudo docker push luminaryn/php:7-pgsql

push-mongo-v8:
	sudo docker push luminaryn/php:7-v8js-mongo

push-mysql-v8:
	sudo docker push luminaryn/php:7-v8js-mysql

push-pgsql-v8:
	sudo docker push luminaryn/php:7-v8js-pgsql

push-dbs: push-mongo push-mysql push-pgsql

push-dbs-v8: push-mongo-v8 push-mysql-v8 push-pgsql-v8

push: push-core push-dbs 

all: build push

