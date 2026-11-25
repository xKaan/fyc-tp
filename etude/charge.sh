#!/usr/bin/env bash
# Lance N clics simultanés sur « Résumer », puis chronomètre l'ouverture
# de la page d'accueil pendant que les appels sont en cours.
#
#   etude/charge.sh       # 10 clics simultanés
#   etude/charge.sh 1     # un seul clic
set -u
N="${1:-10}"
URL="${ETUDE_URL:-http://localhost:8080}"
DIR="$(mktemp -d)"

jeton() { sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' | head -1; }

# Une note à résumer, créée par le formulaire de l'application.
token=$(curl -s -c "$DIR/jar0" "$URL/notes/create" | jeton)
NOTE=$(curl -s -o /dev/null -b "$DIR/jar0" -w "%{redirect_url}" \
  --data-urlencode "_token=$token" --data-urlencode "title=Compte rendu de réunion" \
  --data-urlencode "content=Point d'équipe du lundi : planning, recrutement, budget." \
  "$URL/notes" | sed 's#.*/notes/##')
if [ -z "$NOTE" ]; then echo "Impossible de créer la note : l'application répond-elle sur $URL ?"; exit 1; fi

clic() {
  local i="$1" jar="$DIR/jar$1"
  local token
  token=$(curl -s -c "$jar" "$URL/notes/$NOTE" | jeton)
  curl -s -o /dev/null -b "$jar" -X POST --data-urlencode "_token=$token" \
    -w "clic $i : HTTP %{http_code} après %{time_total} s\n" "$URL/notes/$NOTE/summarize"
}

echo "$N clics sur « Résumer » (note $NOTE)..."
for i in $(seq 1 "$N"); do clic "$i" & done
sleep 2
echo "Pendant ce temps, un autre utilisateur ouvre la page d'accueil..."
curl -s -o /dev/null -w "page d'accueil : HTTP %{http_code} après %{time_total} s\n" "$URL/notes"
wait
echo "État de PHP-FPM depuis le démarrage :"
curl -s "$URL/fpm-status" | grep -E "max active processes|max listen queue|max children reached"
rm -rf "$DIR"
