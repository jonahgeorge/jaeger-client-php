#!/bin/bash

set -e

cd "$(dirname "$0")/.."

git clone https://github.com/uber/jaeger-idl
pushd jaeger-idl

rm -rf ../src/Jaeger/Thrift

FILES=thrift/*.thrift
for f in ${FILES}; do
  thrift -r --gen php:psr4 ${f}
done

rm -rf ../src/Jaeger/Thrift/
mv gen-php/Jaeger/Thrift ../src/Jaeger/Thrift

popd
rm -rf jaeger-idl
