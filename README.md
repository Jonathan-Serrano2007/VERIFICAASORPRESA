# VERIFICA-A-SORPRESA-L

API REST in PHP con Slim Framework per le 10 query richieste su:

- `Fornitori(fid, fnome, indirizzo)`
- `Pezzi(pid, pnome, colore)`
- `Catalogo(fid, pid, costo)`

## 1) Dump database

Il dump è nel file `database_dump.sql` e contiene:

- schema con chiavi primarie/esterne e vincoli
- dati di esempio

### Creazione DB SQLite locale

```bash
sqlite3 database.sqlite < database_dump.sql
```

## 2) Installazione e avvio

```bash
composer install
php -S 0.0.0.0:8080 -t public
```

Entry point alternativi:

- `public/index.php` (consigliato)
- `VERIFICA.php`

Config DB tramite env:

- `DB_DSN` (default: `sqlite:./database.sqlite`)
- `DB_USER`
- `DB_PASSWORD`

## 3) Formato risposta

Tutti gli endpoint rispondono in `application/json`.

Parametri comuni (dove applicati):

- `page` (default `1`)
- `page_size` (default `50`, max `100`)

## 4) Endpoints (10 query)

Base path: `/api`

1. `GET /api/q1`  
	pnome dei pezzi per cui esiste almeno un fornitore

2. `GET /api/q2`  
	fnome dei fornitori che forniscono ogni pezzo

3. `GET /api/q3?colore=rosso`  
	fnome dei fornitori che forniscono tutti i pezzi di un colore (default rosso)

4. `GET /api/q4?fornitore=Acme`  
	pnome dei pezzi forniti da quel fornitore e da nessun altro

5. `GET /api/q5`  
	fid dei fornitori che hanno almeno un pezzo con costo sopra la media di quel pezzo

6. `GET /api/q6`  
	per ciascun pezzo, fnome dei fornitori col costo massimo su quel pezzo

7. `GET /api/q7?colore=rosso`  
	fid dei fornitori che forniscono solo pezzi di quel colore

8. `GET /api/q8?colore1=rosso&colore2=verde`  
	fid dei fornitori che forniscono un pezzo del primo colore e uno del secondo

9. `GET /api/q9?colore1=rosso&colore2=verde`  
	fid dei fornitori che forniscono un pezzo del primo colore o del secondo

10. `GET /api/q10?min_fornitori=2`  
	 pid dei pezzi forniti da almeno N fornitori (N minimo 2)

Health check:

- `GET /health`

## 5) Test unit (facoltativo)

Esempio test in `tests/EndpointsTest.php`.

Esecuzione:

```bash
composer test
```

## 6) Visualizzare gli esiti via web

Con server avviato, apri nel browser:

- `http://127.0.0.1:8080/` (apre `q1` in JSON)
- `http://127.0.0.1:8080/q1` ... `http://127.0.0.1:8080/q10` (una query per pagina, JSON)
- `http://127.0.0.1:8080/api/esiti` (tutte le query insieme, salva e restituisce JSON)

Per esempio, per la query 2 basta aprire `http://127.0.0.1:8080/q2`.

## 7) Esiti tramite API con salvataggio JSON

Genera tutti gli esiti (Q1..Q10) via API e salva su file JSON:

```bash
curl -s "http://127.0.0.1:8080/api/esiti" | jq .
```

Legge il file JSON salvato:

```bash
curl -s "http://127.0.0.1:8080/api/esiti/saved" | jq .
```

Path default file JSON salvato: `storage/esiti.json`.

Opzionale: puoi cambiare il path con variabile ambiente `RESULTS_JSON_PATH`.
