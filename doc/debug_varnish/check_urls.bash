#!/bin/bash

# Options
#   -r <resolve option>
#       for example: www.protegez-vous.ca:80:127.0.0.1
#   -s <session id>
#       for example: 905c3869f7f86ba6aeeec44092017801

while getopts r:s:f: flag
do
    case "${flag}" in
        r) RESOLVE_OPTION=${OPTARG};;
        s) SESSION_ID_OPTION=${OPTARG};;
        f) FILEPATH=${OPTARG};;
    esac
done

if [ -n "$RESOLVE_OPTION" ]; then
  RESOLVE_STRING="--resolve $RESOLVE_OPTION"
else
  RESOLVE_STRING=""
fi

if [ -n "$SESSION_ID_OPTION" ]; then
  COOKIE_STRING="--cookie eZSESSID=$SESSION_ID_OPTION"
else
  COOKIE_STRING=""
fi

mapfile -t URLS < urls.txt

for URL in "${URLS[@]}"
do
  printf "Handling: $URL\n"

  RESPONSE_HEADERS=$(curl -sIXGET $COOKIE_STRING $RESOLVE_STRING "$URL" | grep 'X-Cache\|Age:\|cache-control:\|cooki')

  [[ $RESPONSE_HEADERS =~ X-Cache:[[:space:]](HIT|MISS) ]]

  if [ "${BASH_REMATCH[1]}" = "HIT" ]; then
    printf '%s\n' "${RESPONSE_HEADERS[@]}"
  else
    echo "!!! MISS !!!"
  fi

  printf "\n"
done