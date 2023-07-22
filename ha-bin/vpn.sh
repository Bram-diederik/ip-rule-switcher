#!/bin/bash

TOKEN="12345678"
IP_ADDRESS="$2"
ACTION="$1"
TABLE="$3"
DEVICE="$4"
if [[ -z "$TABLE" ]]; then
  TABLE=0;
fi
if [[ -z "$DEVICE" ]]; then
  DEVICE=0;
fi

if [[ -z "$ACTION" ]] || [[ -z "$IP_ADDRESS" ]]; then
  echo "Usage: vpnControl.sh <add|del> <ip_address> [table] [device]"
  exit 1
elif ! [[ "$ACTION" =~ ^(add|del)$ ]]; then
  echo "Invalid action: $ACTION (must be 'add' or 'del')"
  exit 1
fi
