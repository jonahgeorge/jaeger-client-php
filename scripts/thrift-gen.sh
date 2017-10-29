#!/bin/sh

set -e

cd "$(dirname "$0")/.."

git clone https://github.com/uber/jaeger-idl
pushd jaeger-idl

rm -rf ../src/Jaeger/ThriftGen

FILES=thrift/*.thrift
for f in ${FILES}; do
  thrift -r --gen php:psr4,nsglobal=Jaeger\\ThriftGen ${f}
done

rm -rf ../src/Jaeger/ThriftGen/
mv gen-php/Jaeger/ThriftGen ../src/Jaeger/ThriftGen

popd
rm -rf jaeger-idl
