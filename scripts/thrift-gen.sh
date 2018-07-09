#!/bin/sh

set -e

cd "$(dirname "$0")/.."

# checkout jaeger thrift files
rm -rf jaeger-idl
git clone https://github.com/jaegertracing/jaeger-idl

# define thrift cmd
THRIFT="docker run -u $(id -u) -v '${PWD}:/data' thrift:0.11.0 thrift -o /data/jaeger-idl"
THRIFT_CMD="${THRIFT} --gen php:psr4,oop"

# generate php files
FILES=$(find jaeger-idl/thrift -type f -name \*.thrift)
for f in ${FILES}; do
    echo "${THRIFT_CMD} "/data/${f}""
  eval $THRIFT_CMD "/data/${f}"
done

# move generated files
rm -rf src/Jaeger/Thrift
mv jaeger-idl/gen-php/Jaeger/Thrift src/Jaeger/Thrift

# remove thrift files
rm -rf jaeger-idl
