# Technical Solution Document — Real Connections Feed
**Guised Up — Full-Stack Developer Take-Home Assessment**
Author: Atul Kumar Giri

---

## 1. Understanding the Problem

The Real Connections Feed deliberately rejects engagement-driven ranking (likes/shares/comments)
in favor of four signals: **authenticity**, **relationship depth**, **semantic relevance**, and
**time decay**. The core engineering challenge isn't CRUD — it's designing a scoring pipeline that
blends a relational signal (who you actually talk to) with a semantic signal (vector similarity)
in a way that's fast enough to run per-request for a paginated feed.

My guiding principle for this build: keep the vector search and relational scoring in the *same*
database so I can compute one composite score per post without multiple network hops between
services — important given the 8-hour time box.

---

## 2. System Architecture

```
┌─────────────────────┐
│   React Native App   │
│  (Feed Screen, Search)│
└──────────┬───────────┘
           │ HTTPS (Bearer token via Sanctum)
           ▼
┌───────────────────────────────────────────┐
│              Laravel API Layer              │
│  ─────────────────────────────────────────  │
│  Auth: Sanctum (token-based)                │
│  Controllers: PostController,               │
│    FeedController, SearchController,        │
│    InteractionController                    │
│  Services: FeedRankingService,              │
│    EmbeddingClient (HTTP client to Python)  │
└──────────┬───────────────────┬──────────────┘
           │                   │
           │ SQL + vector ops  │ REST call (text) -> embedding vector
           ▼                   ▼
┌───────────────────────┐   ┌───────────────────────────┐
│   PostgreSQL + pgvector │   │  Python Embedding Service  │
│  ───────────────────── │   │  (FastAPI, single endpoint) │
│  users                 │   │  sentence-transformers      │
│  posts (+ embedding)   │   │  all-MiniLM-L6-v2 (384-dim) │
│  interactions          │   │  POST /embed {text} ->      │
│  follows (optional)    │◄──┤  {vector: float[384]}       │
└───────────────────────┘   └───────────────────────────┘
```

**Request flow for `POST /api/posts`:**
1. Laravel validates input, saves post row.
2. Laravel calls the Python `/embed` service synchronously with the post text.
3. Vector is written back to the `posts.embedding` column (pgvector type).
4. Response returns the created post.

**Request flow for `GET /api/feed`:**
1. Laravel pulls the requesting user's *interest vector* (rolling average embedding of posts
   they've interacted with — computed on read, cached per user for a few minutes).
2. A single SQL query joins `posts`, aggregated `interactions`, and computes cosine distance
   against the interest vector using pgvector's `<=>` operator.
3. `FeedRankingService` applies the weighted scoring formula (below) in the query itself where
   possible, with lightweight post-processing in PHP for the authenticity heuristic.
4. Results are paginated (20/page) and returned.

---

## 3. Database Schema

```sql
-- users
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT now(),
    updated_at TIMESTAMP DEFAULT now()
);

-- posts
CREATE TABLE posts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    text TEXT NOT NULL,
    image_url VARCHAR(2048) NULL,
    has_filter BOOLEAN DEFAULT FALSE,        -- assumption: client reports if image was filtered
    embedding VECTOR(384),                   -- pgvector column
    authenticity_score FLOAT DEFAULT 0,      -- precomputed heuristic, 0-1
    created_at TIMESTAMP DEFAULT now(),
    updated_at TIMESTAMP DEFAULT now()
);
CREATE INDEX idx_posts_user_id ON posts(user_id);
CREATE INDEX idx_posts_created_at ON posts(created_at);
CREATE INDEX idx_posts_embedding_hnsw ON posts USING hnsw (embedding vector_cosine_ops);

-- interactions
CREATE TABLE interactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    post_id BIGINT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL CHECK (type IN ('view','reply','reaction')),
    created_at TIMESTAMP DEFAULT now()
);
CREATE INDEX idx_interactions_user_post ON interactions(user_id, post_id);
CREATE INDEX idx_interactions_post_id ON interactions(post_id);
CREATE INDEX idx_interactions_created_at ON interactions(created_at);

-- follows (optional — supports "not just follow" distinction in brief)
CREATE TABLE follows (
    follower_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    followee_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT now(),
    PRIMARY KEY (follower_id, followee_id)
);
```

**Design notes:**
- `interactions.type` uses a simple enum rather than separate tables, since all three types feed
  the same relationship-depth signal and I want a single indexable table for the D1–D4 SQL queries.
- `has_filter` is a stand-in signal for "polish" — a real system would run actual image analysis,
  but that's out of scope for 8 hours. I flagged this as an assumption rather than silently faking it.
- `authenticity_score` is precomputed at write-time (not per-feed-request) since it depends only on
  post content, not on the viewer — this keeps the feed query cheap.

---

## 4. Vector DB Choice: pgvector

I chose **pgvector** (Postgres extension) over a dedicated vector DB (Pinecone/Weaviate/Qdrant) for
this assessment, for three reasons:

1. **Single source of truth.** The ranking formula needs to combine a vector similarity score with
   relational data (interaction counts, timestamps) in one query. Keeping embeddings in Postgres
   avoids stitching results from two databases and re-joining in application code.
2. **Operational simplicity under time pressure.** No extra service to provision, authenticate to,
   or keep in sync with row-level changes (deletes/updates).
3. **Sufficient at this scale.** HNSW indexing in pgvector performs well up to a few million
   vectors — well beyond what an MVP feed needs.

**Trade-off I'm explicitly noting:** at real production scale (tens of millions of posts, sub-50ms
p99 latency requirements), a dedicated ANN store like Pinecone or Weaviate would out-perform
pgvector and offer better horizontal scaling and managed infra. I'd revisit this choice once the
product has real usage data — this is a "right tool for day one," not "right tool forever" decision.

---

## 5. API Design

All endpoints require `Authorization: Bearer <token>` (Laravel Sanctum) except none — this is a
social feed, so everything is behind auth.

### `POST /api/posts`
```
Request:  { "text": "string", "image_url": "string|null", "has_filter": "boolean|null" }
Response: 201 { "id": 1, "text": "...", "image_url": "...", "authenticity_score": 0.82,
                "created_at": "..." }
```
Server generates the embedding synchronously and computes `authenticity_score` before persisting.

### `GET /api/feed?page=1`
```
Response: 200 {
  "data": [ { "id", "text", "author": {...}, "authenticity_score", "created_at", "score" } ],
  "meta": { "current_page": 1, "per_page": 20, "total": 143 }
}
```

### `GET /api/search?q={query}`
```
Response: 200 { "data": [ { "id", "text", "author": {...}, "similarity": 0.87 } ] } // top 10
```
Embeds the query text via the Python service, then runs a pgvector `<=>` similarity query.

### `POST /api/interactions`
```
Request:  { "post_id": 12, "type": "view" | "reply" | "reaction" }
Response: 201 { "id": 55, "post_id": 12, "type": "view", "created_at": "..." }
```

**Auth strategy:** Sanctum tokens issued at login (`POST /api/login`, seeded with 2 test users per
the brief). Tokens sent as `Authorization: Bearer` headers; no session/cookie auth needed since the
client is a mobile app, not a browser.

---

## 6. Feed Ranking Algorithm

### Plain English
For each candidate post (from users the viewer follows, or has interacted with, or that are
semantically close to their interests), compute four normalized sub-scores between 0 and 1:

- **Authenticity** — precomputed heuristic favoring longer, non-templated text and unfiltered images.
- **Relationship depth** — how often the viewer has interacted with *this specific author*,
  normalized against their most-interacted-with author, so it's relative rather than absolute.
- **Semantic similarity** — cosine similarity between the post's embedding and the viewer's rolling
  "interest vector" (average embedding of posts they reacted to/replied to in the last 30 days).
- **Time decay** — exponential decay so very recent posts get a boost, but a highly relevant
  3-day-old post can still outrank a mediocre 1-hour-old one.

These are combined as a weighted sum, and the feed is sorted descending by this composite score.

### Pseudocode
```
function computeFeedScore(post, viewer):
    authenticity   = post.authenticity_score                       # 0..1, precomputed
    rel_depth_raw  = interaction_count(viewer, post.author, last_30_days)
    rel_depth      = rel_depth_raw / max(1, viewer.max_author_interaction_count)
    semantic_sim   = cosine_similarity(post.embedding, viewer.interest_vector)  # 0..1
    age_hours      = hours_since(post.created_at)
    time_decay     = exp(-LAMBDA * age_hours)   # LAMBDA tuned so ~48h half-life

    W_AUTH, W_REL, W_SEM, W_TIME = 0.25, 0.30, 0.30, 0.15   # sum to 1

    score = (W_AUTH * authenticity)
          + (W_REL  * rel_depth)
          + (W_SEM  * semantic_sim)
          + (W_TIME * time_decay)

    return score

feed = candidate_posts(viewer)              # followed + interacted-with authors + semantic neighbors
for post in feed:
    post.score = computeFeedScore(post, viewer)

return sort_desc(feed, by=score).paginate(20)
```

**Why relationship depth is normalized per-viewer, not global:** a viewer who only ever interacts
with 3 people should still get a strong relationship signal for those 3, rather than being diluted
by comparison to power users with hundreds of connections.

---

## 7. AI Agentic Tool Usage

* **Tool(s) used:** Claude Code and ChatGPT
* **Where it helped most:** ChatGPT helped me understand the project requirements, Docker, React Native, and PostgreSQL concepts, giving me a clear understanding of the overall architecture and workflow. Claude Code assisted in implementing the features, generating boilerplate code, and creating the project documentation.
* **Where I overrode or corrected AI output:** I reviewed and adapted the AI-generated code to fit the project requirements, corrected implementation details where needed, resolved integration issues, and refined the generated content to match the expected behavior and coding standards.
* **Estimated time saved:** Approximately **40–50%** of the overall development time by accelerating learning, development, and documentation.


---

## 8. Trade-offs & Assumptions

1. **Embeddings**: used `sentence-transformers/all-MiniLM-L6-v2` (384-dim, runs locally, no API
   cost) via a small FastAPI service. If unavailable at build time, swapped for a deterministic
   hash-based pseudo-embedding of the same dimensionality purely to keep the pipeline runnable —
   noted inline in code where this swap happens.
2. **"Authenticity" is a heuristic, not ML-verified.** True filter/polish detection would need
   image analysis (out of scope for 8 hours). I scored it from available signals only.
3. **Interest vector is a simple rolling average**, not a trained personalization model — a
   reasonable MVP approximation given the time box.
4. **`follows` table is optional/assumed** — the brief mentions "not just follow" implying a follow
   relationship exists, but no endpoint was specified for it, so I treated it as a lightweight
   addition rather than a required feature.
5. **Candidate set for the feed** (before scoring) is pre-filtered to followed authors, past
   interaction partners, and top-N semantic neighbors — scoring *every* post in the DB per request
   wouldn't scale even at MVP size.
