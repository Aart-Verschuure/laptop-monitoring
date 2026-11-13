#!/data/data/com.termux/files/usr/bin/bash
# Laptop Monitoring - Android agent (Termux + Termux:API)
# Verstuurt telefoonspecificaties, geheugen, opslag, batterij en uptime.
#
# Config in ~/.laptop-monitoring.conf:
#   SERVER_URL="http://192.168.1.10:8000/api/report"
#   TOKEN="..."

set -u
CONF="$HOME/.laptop-monitoring.conf"
[ -f "$CONF" ] || { echo "Config $CONF ontbreekt"; exit 1; }
# shellcheck source=/dev/null
. "$CONF"

prop() { getprop "$1" 2>/dev/null; }
api()  { local out; out=$(timeout 20 "$@" 2>/dev/null); echo "$out" | jq -e 'type == "object"' >/dev/null 2>&1 && echo "$out" || echo '{}'; }
num()  { [[ "${1:-}" =~ ^-?[0-9]+([.][0-9]+)?$ ]] && echo "$1" || echo null; }

# ---------- geheugen (kB -> MB)
mem_total=$(awk '/^MemTotal:/ {print int($2/1024)}' /proc/meminfo)
mem_avail=$(awk '/^MemAvailable:/ {print int($2/1024)}' /proc/meminfo)
mem_used=$(( mem_total - mem_avail ))

# ---------- opslag (interne opslag)
read -r disk_total_kb disk_used_kb < <(df -k /data 2>/dev/null | awk 'NR==2 {print $2, $3}')
disk_total_gb=$(awk -v k="${disk_total_kb:-0}" 'BEGIN {printf "%.1f", k/1048576}')
disk_used_gb=$(awk -v k="${disk_used_kb:-0}" 'BEGIN {printf "%.1f", k/1048576}')

# ---------- uptime
# Sommige Android-versies blokkeren /proc/uptime voor apps. Val dan terug op
# ro.runtime.firstboot: het tijdstip (in ms) waarop Android is opgestart.
booted_at=null
uptime_s=$(awk '{print int($1)}' /proc/uptime 2>/dev/null)
if [[ "$uptime_s" =~ ^[0-9]+$ ]]; then
  booted_at=$(date -d "@$(( $(date +%s) - uptime_s ))" -Iseconds)
else
  firstboot=$(prop ro.runtime.firstboot)
  [[ "$firstboot" =~ ^[0-9]{10,}$ ]] && booted_at=$(date -d "@$(( firstboot / 1000 ))" -Iseconds)
fi
[ "$booted_at" = null ] || booted_at="\"$booted_at\""

# ---------- batterij
battery=$(api termux-battery-status)
bat_pct=$(echo "$battery" | jq -r '.percentage // empty')
bat_temp=$(echo "$battery" | jq -r '.temperature // empty')
bat_status=$(echo "$battery" | jq -r '.status // empty')
bat_health=$(echo "$battery" | jq -r '.health // empty')
case "$bat_status" in
  CHARGING|FULL) charging=true ;;
  DISCHARGING|NOT_CHARGING) charging=false ;;
  *) charging=null ;;
esac

# ---------- netwerk / simkaart
tel=$(api termux-telephony-deviceinfo)
wifi=$(api termux-wifi-connectioninfo)

# ---------- CPU-info
cores=$(nproc 2>/dev/null)
max_khz=$(cat /sys/devices/system/cpu/cpu*/cpufreq/cpuinfo_max_freq 2>/dev/null | sort -n | tail -1)
max_ghz=$([ -n "$max_khz" ] && awk -v k="$max_khz" 'BEGIN {printf "%.2f GHz", k/1000000}')
soc="$(prop ro.soc.manufacturer) $(prop ro.soc.model)"
[ -z "${soc// /}" ] && soc="$(prop ro.board.platform) $(prop ro.hardware)"

marketing=$(prop ro.product.marketname)
[ -z "$marketing" ] && marketing=$(prop ro.config.marketing_name)

payload=$(jq -n \
  --arg merk "$(prop ro.product.brand)" \
  --arg fabrikant "$(prop ro.product.manufacturer)" \
  --arg model "$(prop ro.product.model)" \
  --arg naam "$marketing" \
  --arg codenaam "$(prop ro.product.device)" \
  --arg android "$(prop ro.build.version.release)" \
  --arg sdk "$(prop ro.build.version.sdk)" \
  --arg patch "$(prop ro.build.version.security_patch)" \
  --arg build "$(prop ro.build.display.id)" \
  --arg kernel "$(uname -r)" \
  --arg chipset "$soc" \
  --arg abi "$(prop ro.product.cpu.abilist)" \
  --arg cores "$cores" \
  --arg maxfreq "$max_ghz" \
  --arg dpi "$(prop ro.sf.lcd_density)" \
  --arg ram "$(awk -v m="$mem_total" 'BEGIN {printf "%.1f GB", m/1024}')" \
  --arg opslag "$disk_total_gb GB" \
  --arg bathealth "$bat_health" \
  --argjson tel "$tel" \
  --argjson wifi "$wifi" \
  --argjson cpu null \
  --argjson mem_used "$(num "$mem_used")" \
  --argjson mem_total "$(num "$mem_total")" \
  --argjson disk_used "$(num "$disk_used_gb")" \
  --argjson disk_total "$(num "$disk_total_gb")" \
  --argjson bat_pct "$(num "$bat_pct")" \
  --argjson bat_temp "$(num "$bat_temp")" \
  --argjson charging "$charging" \
  --argjson booted_at "$booted_at" \
  --arg now "$(date -Iseconds)" \
  '{
    specs: ({
      merk: $merk, fabrikant: $fabrikant, model: $model, naam: $naam, codenaam: $codenaam,
      android_versie: $android, api_level: $sdk, beveiligingspatch: $patch, build: $build, kernel: $kernel,
      chipset: $chipset, cpu_architectuur: $abi, cpu_kernen: $cores, max_kloksnelheid: $maxfreq,
      werkgeheugen: $ram, opslag: $opslag, scherm_dichtheid_dpi: $dpi,
      batterij_gezondheid: $bathealth,
      provider: ($tel.network_operator_name // null), sim_provider: ($tel.sim_operator_name // null),
      netwerktype: ($tel.data_network_type // $tel.network_type // null),
      telefoontype: ($tel.phone_type // null), roaming: ($tel.network_roaming // null),
      wifi_netwerk: ($wifi.ssid // null), wifi_ip: ($wifi.ip // null),
      wifi_snelheid: (if $wifi.link_speed_mbps then "\($wifi.link_speed_mbps) Mbps" else null end)
    } | with_entries(select(.value != null and .value != ""))),
    metrics: {
      cpu_percent: $cpu, memory_used_mb: $mem_used, memory_total_mb: $mem_total,
      disk_used_gb: $disk_used, disk_total_gb: $disk_total,
      battery_percent: $bat_pct, battery_temperature: $bat_temp, charging: $charging,
      booted_at: $booted_at, recorded_at: $now
    }
  }')

curl -sS -m 30 -X POST "$SERVER_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$payload" -o /dev/null -w "%{http_code}\n"
