#!/bin/bash

# Options
#   -u <url>
#      example: /path/to/article
#   -r <resolve option>
#       for example: 127.0.0.1
#   -d <domain>
#   -p <port>
#   -s <session cookie value>
#       for example: 905c3869f7f86ba6aeeec44092017801
#   -h <user hash>
#       for example: 4bee787753ed6173e382f569d3a33716e14d1a16e7dbaf810d80e06373aa0746

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

while getopts u:r:s:d:p:h: flag
do
    case "${flag}" in
        u) URL_OPTION=${OPTARG};;
        r) RESOLVE_OPTION=${OPTARG};;
        s) SESSION_ID_OPTION=${OPTARG};;
        d) DOMAIN_OPTION=${OPTARG};;
        p) PORT_OPTION=${OPTARG};;
    	h) USER_HASH_OPTION=${OPTARG};;
    esac
done

if [ -n "$PORT_OPTION" ]; then
  PORT=":$PORT_OPTION"
else
  PORT=""
fi

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

printf "\nHandling $BASE_URL$PORT$URL_OPTION\n"

# User hash handling
if [ -n "$USER_HASH_OPTION" ]; then
	USER_HASH="$USER_HASH_OPTION"
else
	printf " Fetching User Hash"
	RESPONSE_HEADERS=$(curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header 'Surrogate-Capability: abc=ESI/1.0' --header 'accept: application/vnd.fos.user-context-hash' --header "x-fos-original-url: $URL_OPTION" $BASE_URL$PORT/_fos_user_context_hash | grep 'Set-Cookie:\|X-User-Context-Hash:')

	[[ $RESPONSE_HEADERS =~ X-User-Context-Hash:[[:space:]]([0-9a-z]+) ]]

	USER_HASH=${BASH_REMATCH[1]}
fi
printf " User Hash: $USER_HASH\n"

#Main request
printf " Main page\n"
RESPONSE=$(curl -IXGET $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$PORT$URL_OPTION")
echo "$RESPONSE"

printf " ESI blocks:\n"
RESPONSE=$(curl -s $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$PORT$URL_OPTION" | grep -Po "<esi:include src=\K\".*?\"" )

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
  RESPONSE_HEADERS=$(curl -sIXGET $RESOLVE_STRING $COOKIE_STRING --header "Surrogate-Capability: abc=ESI/1.0" --header "x-user-context-hash: $USER_HASH" "$BASE_URL$PORT$ESI_URL")

  printf '%s\n' "${RESPONSE_HEADERS[@]}"

  ITER=$(expr $ITER + 1)
done
