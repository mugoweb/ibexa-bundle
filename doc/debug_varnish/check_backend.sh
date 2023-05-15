#!/bin/bash

# Options
#   -u <url>
#      example: /
#   -r <resolve option>
#       for example: 127.0.0.1
#   -d <domain>
#   -p <port>
#   -s <session cookie value>
#       for example: 905c3869f7f86ba6aeeec44092017801

while getopts u:r:s:d:p: flag
do
    case "${flag}" in
        u) URL_OPTION=${OPTARG};;
        r) RESOLVE_OPTION=${OPTARG};;
        s) SESSION_ID_OPTION=${OPTARG};;
        d) DOMAIN_OPTION=${OPTARG};;
        p) PORT_OPTION=${OPTARG};;
    esac
done

if [ "$PORT_OPTION" = "443" ]; then
    BASE_URL = "https://$DOMAIN_OPTION"
  else
    BASE_URL = "http://$DOMAIN_OPTION"
  fi

if [ -n "$RESOLVE_OPTION" ]; then
  RESOLVE_STRING="--resolve $DOMAIN_OPTION:$PORT_OPTION:$RESOLVE_OPTION"
else
  RESOLVE_STRING=""
fi

if [ -n "$SESSION_ID_OPTION" ]; then
  COOKIE_STRING=$"--cookie eZSESSID=$SESSION_ID_OPTION"
else
  COOKIE_STRING=""
fi

RESPONSE_HEADERS=$(curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header 'Surrogate-Capability: abc=ESI/1.0' --header 'accept: application/vnd.fos.user-context-hash' --header "x-fos-original-url: $URL_OPTION" $BASE_URL/_fos_user_context_hash | grep 'Set-Cookie:\|X-User-Context-Hash:')

[[ $RESPONSE_HEADERS =~ X-User-Context-Hash:[[:space:]]([0-9a-z]+) ]]

USER_HASH=${BASH_REMATCH[1]}
printf "Handling $URL_OPTION\n"
printf " User Hash: $USER_HASH\n"

printf "Main page\n"
curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" http://www.protegez-vous.ca/ | grep 'Cache-Control:'

printf "ESI blocks:\n"
RESPONSE=$(curl -s $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" http://www.protegez-vous.ca/ | grep -Po "<esi:include src=\K\".*?\"" )

for ESI_LINE in $RESPONSE; do
  temp="${ESI_LINE%\"}"
  temp="${temp#\"}"
  echo "$temp"
done

