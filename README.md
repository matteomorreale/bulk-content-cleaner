# Bulk Content Cleaner

**Versione:** 1.0.1  
**Autore:** Matteo Morreale  
**Licenza:** GPL-2.0+  
**Compatibilità WordPress:** 5.8+  
**PHP minimo:** 7.4+

---

## Descrizione

**Bulk Content Cleaner** è un plugin WordPress per l'eliminazione massiva di post, pagine e media dalla libreria multimediale. L'operazione avviene tramite chiamate AJAX progressive (batch), garantendo stabilità anche su installazioni con migliaia di contenuti, senza rischi di timeout del server.

---

## Funzionalità

| Funzionalità | Dettaglio |
|---|---|
| Eliminazione post e pagine | Rimuove definitivamente tutti i post e le pagine (bypass del cestino) |
| Eliminazione media allegati | Rimuove i file media associati ai post eliminati |
| Eliminazione media standalone | Rimuove tutti i media dalla libreria indipendentemente dai post |
| Batch size configurabile | L'utente sceglie quanti elementi eliminare per ogni chiamata AJAX (default: 5, max: 100) |
| Chiamate AJAX idempotenti | Ogni richiesta verifica il nonce WordPress; gli elementi già eliminati vengono saltati senza errori |
| Log in tempo reale | Console dark-mode con timestamp, icone e codice colore per successi, errori, warning e info |
| Statistiche live | Contatori aggiornati in tempo reale: eliminati, errori, saltati, chiamate AJAX effettuate |
| Interruzione manuale | Il pulsante "Interrompi" ferma il processo al termine del batch corrente |
| Interfaccia moderna | Design responsive con CSS custom, progress bar animata e pannelli collassabili |

---

## Installazione

1. Scaricare il file `bulk-content-cleaner.zip`.
2. Accedere al pannello di amministrazione WordPress.
3. Navigare in **Plugin → Aggiungi nuovo → Carica plugin**.
4. Selezionare il file ZIP e cliccare su **Installa ora**.
5. Attivare il plugin.
6. Il menu **Bulk Cleaner** apparirà nella barra laterale dell'amministrazione.

---

## Utilizzo

### Accesso alla pagina

Dopo l'attivazione, navigare in **Bulk Cleaner** nel menu laterale dell'amministrazione WordPress.

### Configurazione

| Opzione | Descrizione |
|---|---|
| **Post e Pagine** | Se selezionato, elimina tutti i post e le pagine presenti nel database |
| **Media allegati** | Se selezionato insieme a "Post e Pagine", elimina anche i media associati a ogni post; se selezionato da solo, elimina tutti i media dalla libreria |
| **Batch size** | Numero di elementi elaborati per ogni singola chiamata AJAX. Valori consigliati: 5–20 per server condivisi, fino a 50–100 per VPS dedicati |

### Avvio dell'operazione

1. Selezionare il tipo di contenuto da eliminare.
2. Impostare il batch size desiderato.
3. Cliccare su **Avvia Eliminazione**.
4. Confermare il dialogo di avviso.
5. Monitorare il progresso tramite la barra di avanzamento, i contatori statistici e il log.

### Interruzione

Cliccare su **Interrompi** per fermare il processo. Il batch corrente verrà completato prima dell'arresto.

---

## Architettura

Il plugin segue il paradigma **MVC** ed è strutturato come segue:

```
bulk-content-cleaner/
├── bulk-content-cleaner.php          # Entry point del plugin (Controller principale)
├── README.md                         # Documentazione
├── includes/
│   ├── class-bulk-content-cleaner.php        # Classe principale (bootstrap)
│   └── class-bulk-content-cleaner-loader.php # Hook loader (registra azioni e filtri)
└── admin/
    ├── class-bulk-content-cleaner-admin.php  # Controller admin + handler AJAX
    └── views/
    │   └── admin-display.php                 # View HTML della pagina admin
    └── assets/
        ├── js/
        │   └── bulk-content-cleaner-admin.js # Logica frontend AJAX
        └── css/
            └── bulk-content-cleaner-admin.css # Stili interfaccia
```

---

## Sicurezza

- **Nonce WordPress:** ogni chiamata AJAX include un nonce generato con `wp_create_nonce('bcc_delete_nonce')` e verificato server-side con `wp_verify_nonce()`.
- **Capability check:** l'handler AJAX verifica che l'utente abbia il permesso `manage_options` prima di eseguire qualsiasi operazione.
- **Sanitizzazione input:** tutti i parametri POST vengono sanitizzati con `absint()`, `sanitize_text_field()` e `rest_sanitize_boolean()`.
- **Idempotenza:** se un elemento è già stato eliminato, viene semplicemente saltato senza generare errori, rendendo sicura la ri-esecuzione della stessa richiesta.
- **Accesso diretto bloccato:** tutti i file PHP verificano la costante `WPINC` per prevenire l'accesso diretto.

---

## Prefissi utilizzati

Per evitare conflitti con altri plugin, il codice utilizza i seguenti prefissi univoci:

| Tipo | Prefisso |
|---|---|
| Classi PHP | `Bulk_Content_Cleaner_` |
| Costanti | `BCC_` |
| Azioni AJAX | `bcc_` |
| ID/classi CSS | `bcc-` |
| Variabile JS globale | `bcc_ajax` |
| Variabile JS locale | `bcc` |

---

## Changelog

### 1.0.1 - 2026-03-9
- Risolto problema di eliminazione incompleta (Logica di paginazione errata): Il plugin utilizzava un sistema di offset progressivo (es. pagina 1, pagina 2, pagina 3...) anche durante l'eliminazione.

### 1.0.0 — 2026-03-06
- Prima release stabile.
- Eliminazione bulk di post, pagine e media tramite AJAX progressivo.
- Interfaccia admin moderna con log in tempo reale.
- Chiamate AJAX idempotenti con nonce di sicurezza.
- Batch size configurabile (1–100).
- Pulsante di interruzione manuale.

---

## Licenza

Questo plugin è distribuito sotto licenza [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.txt).
