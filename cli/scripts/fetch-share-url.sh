#!/usr/bin/env bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php $DIR/../squire.php fetch-share-url | pbcopy
