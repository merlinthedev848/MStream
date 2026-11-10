#!/bin/bash

ENABLE_REDIS=${ENABLE_REDIS:-true}
export ENABLE_REDIS

dockerize -template "/var/mstream/centrifugo/config.toml.tmpl:/var/mstream/centrifugo/config.toml"
