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

function urldecode() { : "${*//+/ }"; echo -e "${_//%/\\x}"; }

function parseQuery {
    local querystring="$*"
    echo -n "("
    echo "${querystring}" | sed 's/&/\n/g' | while IFS== read arg value
    do
        echo -n "[${arg}]='${value}' "
    done
    echo ")"
}

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
  BASE_URL="https://$DOMAIN_OPTION"
else
  BASE_URL="http://$DOMAIN_OPTION"
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

# Fetch user hash
RESPONSE_HEADERS=$(curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header 'Surrogate-Capability: abc=ESI/1.0' --header 'accept: application/vnd.fos.user-context-hash' --header "x-fos-original-url: $URL_OPTION" $BASE_URL/_fos_user_context_hash | grep 'Set-Cookie:\|X-User-Context-Hash:')

[[ $RESPONSE_HEADERS =~ X-User-Context-Hash:[[:space:]]([0-9a-z]+) ]]

USER_HASH=${BASH_REMATCH[1]}
printf "Handling $URL_OPTION\n"
printf " User Hash: $USER_HASH\n"

#printf " Main page\n"
#curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$URL_OPTION" | grep 'Cache-Control:'

printf " ESI blocks:\n"
RESPONSE=$(curl -s $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$URL_OPTION" | grep -Po "<esi:include src=\K\".*?\"" )

ITER=0
for ESI_LINE in $RESPONSE; do
  ESI_URL="${ESI_LINE%\"}"
  ESI_URL="${ESI_URL#\"}"

  [[ $ESI_URL =~ _path=(.*) ]]

  PATH_PARAMETER=$(urldecode "${BASH_REMATCH[1]}")

  declare -A querydict=$(parseQuery "${PATH_PARAMETER}" )

  printf "#### $ITER ####\n"
  for i in "${!querydict[@]}"
  do
    if [ $i = "_controller" ]; then
      echo "$i: " $(urldecode "${querydict[$i]}")
    elif [ $i = "serialized_siteaccess_matcher" ]; then
      NOBASHNERD=1
    elif [ $i = "serialized_siteaccess" ]; then
      NOBASHNERD=1
    else
      echo "$i: ${querydict[$i]}"
    fi
  done
  printf "ESI response:\n"
  RESPONSE_HEADERS=$(curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$ESI_URL")

  printf '%s\n' "${RESPONSE_HEADERS[@]}"

  ITER=$(expr $ITER + 1)
done
