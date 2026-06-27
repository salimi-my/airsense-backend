# Appendix C — Test Evidence

Save screenshots and logs in **this folder** (`airsense-backend/tests/evidence/`) using the naming convention `[TestID]_evidence.[ext]`.

AirSense uses separate repositories ([airsense-backend](https://github.com/salimi-my/airsense-backend), [airsense-frontend](https://github.com/salimi-my/airsense-frontend), [airsense-ai on Hugging Face](https://huggingface.co/spaces/salimi-my/airsense-ai)). All STD Appendix C evidence is stored here in the backend repo alongside PHPUnit output and API test artifacts.

## Hugging Face AI service

| Item | URL |
|------|-----|
| **`AI_SERVICE_URL` (API base)** | `https://salimi-my-airsense-ai.hf.space` |
| HF repo (source only, not API) | https://huggingface.co/spaces/salimi-my/airsense-ai |
| Health check | `GET https://salimi-my-airsense-ai.hf.space/health` |
| Risk classify (STD) | `POST https://salimi-my-airsense-ai.hf.space/predict` |
| Warmup | `GET https://salimi-my-airsense-ai.hf.space/warmup` |

In backend `.env`: `AI_SERVICE_URL=https://salimi-my-airsense-ai.hf.space` (no trailing slash).

## Setup (one-time)

**Terminal 1 — Backend**
```bash
cd airsense-backend
cp .env.example .env
# Set WAQI_API_TOKEN, DB credentials, and:
# AI_SERVICE_URL=https://salimi-my-airsense-ai.hf.space
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

**Terminal 2 — AI service (optional)** — only if you run FastAPI locally instead of Hugging Face (e.g. for `Pytest_evidence.png`). If so, set `AI_SERVICE_URL=http://127.0.0.1:8001` in `.env` and run:
```bash
cd airsense-ai
python3 -m venv .venv && source .venv/bin/activate   # skip if .venv already exists
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8001
```

Run Python tests (from `airsense-ai`, with venv activated):
```bash
source .venv/bin/activate
pip install -r requirements.txt
python -m pytest tests/ -v
```

> **Install error on Python 3.14?** Older pinned `pydantic==2.10.6` cannot build on 3.14. Use current `requirements.txt` (`pydantic>=2.12`). If install still fails, remove `.venv` and recreate: `rm -rf .venv && python3 -m venv .venv && source .venv/bin/activate && pip install -r requirements.txt`

**Terminal 3 — Frontend** (separate repo)
```bash
cd airsense-frontend
cp .env.example .env.local
# Set NEXT_PUBLIC_BACKEND_URL=http://localhost:8000
pnpm install && pnpm dev
```

**Login:** `admin@airsense.test` / `P@$$w0rd` (or `johndoe@example.com` / `P@$$w0rd`)

## Evidence checklist

| File | What to capture | How |
|------|-----------------|-----|
| `UT_evidence.png` | PHPUnit all green | `php artisan test` (from backend root) |
| `Pytest_evidence.png` | Python all green | `source .venv/bin/activate && python -m pytest tests/ -v` in `airsense-ai` |
| `ST-01_evidence.png` | Map + 5 markers | Login → `http://localhost:3000/map` |
| `ST-04_evidence.png` | Form + RiskCard | `/assess` → PJ, elderly, asthma, strenuous exercise → submit |
| `ST-05_evidence.png` | Red personal alert banner | Same as ST-04 when risk is High/Critical |
| `ST-06_evidence.png` | Network timing | DevTools → 5× submit assess → screenshot `/api/assessments` timings |
| `ST-07_evidence.png` | Mobile 375px | DevTools iPhone SE → `/map` and `/assess` |
| `AI-01_evidence.png` | Postman `/predict` | `POST https://salimi-my-airsense-ai.hf.space/predict` body: `{ "aqi": 120, "pm25": 45, "age_group": "adult", "condition": "none", "activity": "moderate" }` |
| `AI-04_evidence.png` | Concurrency test | `python -m pytest tests/test_concurrency.py -v` in `airsense-ai` (venv active) |
| `CT-04_evidence.png` | No secrets in bundle | DevTools search for `WAQI_API_TOKEN` / `AI_SERVICE` → 0 results |

Optional: `ST-08_evidence.png` (`/admin/logs`), `ST-09_evidence.png` (`php artisan app:fetch-aqi-data`).

After capture, commit evidence files to the **airsense-backend** repository.
