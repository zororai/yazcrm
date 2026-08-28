# YazCRM ASR Service

Standalone speech-to-text microservice for the YazCRM Laravel app. FastAPI + PyTorch +
Hugging Face Transformers. This is Phase 1 only: the Python service itself, callable
directly via HTTP. Laravel integration (queue job, DB storage, UI) is a later phase.

## Setup

```bash
cd asr-service
python -m venv venv
venv\Scripts\activate        # Windows
# source venv/bin/activate   # macOS/Linux

pip install -r requirements.txt
copy .env.example .env       # Windows
# cp .env.example .env       # macOS/Linux
```

Edit `.env` and set `ASR_SERVICE_KEY` to a real secret. `ASR_MODEL_SHONA` is
preconfigured to `badrex/w2v-bert-2.0-shona-asr`. `ASR_MODEL_ENGLISH` and
`ASR_MODEL_NDEBELE` are left blank on purpose — requesting those languages
returns a clear configuration error instead of silently using the Shona model.

ffmpeg must be on PATH (used to normalize incoming audio to mono 16kHz WAV
before it reaches the model, since Yeastar recordings aren't guaranteed to
share one codec).

## Run

```bash
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

The Shona model is **not** downloaded at process start — it downloads from
Hugging Face and loads into memory on the *first* `/transcribe` request for
that language, then stays resident for the lifetime of the process (no
reload per request). First request will be slow while the model downloads;
subsequent requests reuse it.

## API

### `GET /health`
No auth required.
```json
{ "status": "ok" }
```

### `GET /models`
Requires `Authorization: Bearer <ASR_SERVICE_KEY>`.
```json
{
  "models": [
    { "language": "shona", "model": "badrex/w2v-bert-2.0-shona-asr", "enabled": true },
    { "language": "english", "model": "", "enabled": false },
    { "language": "ndebele", "model": "", "enabled": false }
  ]
}
```

### `POST /transcribe`
Requires `Authorization: Bearer <ASR_SERVICE_KEY>`. `multipart/form-data`:

| field    | required | notes                                   |
|----------|----------|------------------------------------------|
| audio    | yes      | wav / mp3 / ogg / m4a                    |
| language | yes      | `shona`, `english`, or `ndebele`         |
| model    | no       | override the configured model by name    |

Example:
```bash
curl -X POST http://127.0.0.1:8000/transcribe \
  -H "Authorization: Bearer change-this" \
  -F "audio=@call.wav" \
  -F "language=shona"
```

Success response:
```json
{
  "success": true,
  "language": "shona",
  "language_code": "sn",
  "transcript": "Ndiri kuda rubatsiro...",
  "confidence": 0.87,
  "model": "badrex/w2v-bert-2.0-shona-asr",
  "processing_time_ms": 4213
}
```

Error response (e.g. unconfigured language, bad audio, model failure) —
always `422` or `500`, never a raw stack trace:
```json
{ "success": false, "error": "No ASR model configured for language 'ndebele'. ..." }
```

### Confidence caveat
`confidence` is the mean per-frame max softmax probability from the CTC
output — a rough signal, **not** a calibrated likelihood or a substitute for
a real Word Error Rate evaluation (see spec §23, not yet done).

## CPU / RAM requirements

`w2v-bert-2.0` is a ~600M-parameter model. Expect:
- **RAM**: 3–4GB free while the model is loaded (CPU inference).
- **CPU**: runs on CPU; a 30–60s phone call takes roughly 5–20s to transcribe
  on a modern multi-core CPU, longer on constrained hardware. Set `DEVICE=cuda`
  in `.env` if a CUDA GPU is available — untested in this environment (no GPU
  present here).
- **Disk**: the Shona model weights are ~2.4GB, cached under the Hugging Face
  cache dir (`~/.cache/huggingface` by default) after first download.

## Known limitations (Phase 1)

- Only Shona is transcribable out of the box. English/Ndebele require setting
  `ASR_MODEL_ENGLISH` / `ASR_MODEL_NDEBELE` to a real model — none is assumed
  or substituted.
- No code-switching support (see main spec §24) — a single language is
  assumed per request.
- No automatic language detection — the caller must specify `language`.
- `confidence` is an approximation, not a proper WER-based accuracy figure.
- Not yet load-tested for concurrent requests; one worker process only.
