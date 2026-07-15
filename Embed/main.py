"""
Guised Up — Embedding microservice.

Exposes a single endpoint that turns post/query text into a 384-dim vector
using sentence-transformers (all-MiniLM-L6-v2). Called synchronously by the
Laravel API for both post creation and search.

Run: uvicorn main:app --reload --port 8000
"""

from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer

app = FastAPI(title="Guised Up Embedding Service")

# Loaded once at startup — 384-dim, small and fast enough to run on CPU
model = SentenceTransformer("all-MiniLM-L6-v2")


class EmbedRequest(BaseModel):
    text: str


class EmbedResponse(BaseModel):
    vector: list[float]


@app.post("/embed", response_model=EmbedResponse)
def embed(payload: EmbedRequest):
    vector = model.encode(payload.text).tolist()
    return {"vector": vector}


@app.get("/health")
def health():
    return {"status": "ok"}
