# Guised Up — Full-Stack Developer Take-Home Assessment

**Author:** Atul Kumar Giri

## What's included

- `/backend` — Laravel API: migrations (incl. pgvector), models, services (`EmbeddingClient`, `FeedRankingService`), 4 controllers, routes, seeder (2 test users), 4 feature tests
- `/embed-service` — Python FastAPI microservice generating 384-dim embeddings via sentence-transformers
- `/GuisedUpApp` — React Native (Expo) Feed Screen: paginated feed, infinite scroll, natural-language search, reaction button, loading/empty/error states
- `/sql/queries.sql` — SQL Challenge queries D1–D4
- `/Document/TSD.md` — Technical Solution Document (architecture, schema, vector DB choice, ranking algorithm, trade-offs, AI tool usage)

## Setup

### 1. Database (Postgres + pgvector, via Docker)
```bash
docker run --name guisedup-pg -e POSTGRES_PASSWORD=password -e POSTGRES_DB=guisedup \
  -p 5432:5432 -d pgvector/pgvector:pg16
```
On subsequent runs, just: `docker start guisedup-pg`

### 2. Python embedding service
```bash
cd embed
python -m venv venv && source venv/bin/activate   # Windows: venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8001
```

### 3. Laravel backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=0.0.0.0 --port=8000
```
`--host=0.0.0.0` is required so the React Native app (running on a phone) can reach the API over the local network.

### 4. React Native app (Expo)
```bash
cd App
npm install
```
Edit `api/client.js` and set `API_BASE_URL` to your machine's local network IP (find it via `ipconfig` on Windows), e.g.:
```js
const API_BASE_URL = 'http://192.168.1.108:8000/api';
```
Then:
```bash
npx expo start
```
Scan the QR code with the Expo Go app (phone must be on the same Wi-Fi network as your PC).

### 5. Run backend tests
```bash
cd backend
php artisan test
```

## Test users (seeded)
| Email | Password |
|---|---|
| alice@example.com | password |
| bob@example.com | password |

The app auto-logs in as Alice on launch (see `App.js` / `src/app/index.tsx`) — no login screen was built since auth flow wasn't the focus of this assessment.

## API reference

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/login` | No | Returns Sanctum token |
| POST | `/api/posts` | Yes | Create post, auto-generates embedding |
| GET | `/api/feed?page=1` | Yes | Personalized ranked feed, 20/page |
| GET | `/api/search?q=...` | Yes | Semantic search, top 10 results |
| POST | `/api/interactions` | Yes | Log view/reply/reaction |

## Design decisions & trade-offs

Full reasoning is in `/Document/TSD.md`, including:
- Why pgvector was chosen over a dedicated vector DB (Pinecone/Weaviate)
- The exact weighted feed-ranking formula (authenticity, relationship depth, semantic similarity, time decay)
- The authenticity-score heuristic (no ML image analysis — out of scope for the time box)
- Fallback behavior if the embedding service is unreachable (deterministic hash-based pseudo-embedding)

## AI tool usage

Documented honestly in `/Document/TSD.md` Section 7, per the brief's requirement.

## What I'd do with more time

- A real login screen instead of the hardcoded test-user auto-login
- Move the feed-ranking SQL into a materialized/cached view for better performance at scale
- Replace the heuristic authenticity score with actual image-metadata analysis
- Add a dedicated test database instead of running feature tests against the dev database
