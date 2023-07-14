#!/bin/bash

TOKEN="12345678"
IP_ADDRESS="$2"
ACTION="$1"
TABLE="$3"

if [[ -z "$TABLE" ]]; then
  TABLE=0;
fi

if [[ -z "$ACTION" ]] || [[ -z "$IP_ADDRESS" ]]; then
  echo "Usage: vpnControl.sh <add|del> <ip_address> [table]"
  exit 1
elif ! [[ "$ACTION" =~ ^(add|del)$ ]]; then
  echo "Invalid action: $ACTION (must be 'add' or 'del')"
  exit 1
fi

RESPONSE=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" http://192.168.5.1/vpn/?action=$ACTION\&addr=$IP_ADDRESS\&table=$TABLE)

echo curl -s -X POST -H "Authorization: Bearer $TOKEN" http://192.168.5.1/vpn/?action=$ACTION\&addr=$IP_ADDRESS\&table=$TABLE

if [[ "$RESPONSE" =~ "wrong token" ]]; then
  echo "Invalid token"
  exit 1
fi

if [[ "$RESPONSE" =~ "Error" ]]; then
  echo "Error executing request: $RESPONSE"
  exit 1
fi
echo $RESPONSE;
echo "Command executed successfully"
exit 0
