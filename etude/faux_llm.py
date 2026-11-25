"""Faux fournisseur d'IA pour l'étude de cas S1.2.

Répond comme l'API de chat de Mistral, mais après LLM_DELAI secondes.
Chaque appel reçu est journalisé : chez un vrai fournisseur, il serait
facturé, que le client attende la réponse ou non.
"""

import json
import os
import time
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

DELAI = float(os.environ.get("LLM_DELAI", "8"))
compteur = 0


def journal(message):
    print(f"[{datetime.now():%H:%M:%S}] {message}", flush=True)


class Fournisseur(BaseHTTPRequestHandler):
    def do_POST(self):
        global compteur
        compteur += 1
        numero = compteur
        taille = int(self.headers.get("Content-Length", 0))
        self.rfile.read(taille)
        journal(f"appel n° {numero} reçu ({taille} octets), facturé. Réponse dans {DELAI:g} s")
        time.sleep(DELAI)
        corps = json.dumps({
            "choices": [{"message": {"role": "assistant",
                                     "content": f"Résumé n° {numero}, produit après {DELAI:g} s."}}]
        }).encode()
        try:
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(corps)))
            self.end_headers()
            self.wfile.write(corps)
            journal(f"appel n° {numero} : réponse envoyée")
        except (BrokenPipeError, ConnectionResetError):
            journal(f"appel n° {numero} : le client était déjà parti, réponse perdue")

    def log_message(self, *args):
        pass


journal(f"faux fournisseur prêt, délai de réponse {DELAI:g} s")
ThreadingHTTPServer(("0.0.0.0", 8000), Fournisseur).serve_forever()
