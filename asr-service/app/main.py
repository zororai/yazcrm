import logging
import tempfile
from pathlib import Path

from fastapi import Depends, FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.config import get_settings
from app.schemas.transcription import HealthResponse, ModelInfo, ModelsResponse, TranscriptionResponse
from app.services.transcription import TranscriptionError, validate_upload, transcribe as run_transcription

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("asr.main")

app = FastAPI(title="YazCRM ASR Service", version="0.1.0")

bearer_scheme = HTTPBearer(auto_error=False)


def verify_api_key(credentials: HTTPAuthorizationCredentials | None = Depends(bearer_scheme)) -> None:
    settings = get_settings()
    if not credentials or credentials.credentials != settings.asr_service_key:
        raise HTTPException(status_code=401, detail="Invalid or missing API key.")


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse(status="ok")


@app.get("/models", response_model=ModelsResponse, dependencies=[Depends(verify_api_key)])
def models() -> ModelsResponse:
    settings = get_settings()
    return ModelsResponse(models=[
        ModelInfo(language=lang, model=model_name, enabled=bool(model_name))
        for lang, model_name in settings.models_by_language().items()
    ])


@app.post("/transcribe", dependencies=[Depends(verify_api_key)])
async def transcribe(
    audio: UploadFile = File(...),
    language: str = Form(...),
    model: str | None = Form(default=None),
):
    contents = await audio.read()

    try:
        validate_upload(audio.filename or "", len(contents))
    except TranscriptionError as e:
        return JSONResponse(status_code=422, content={"success": False, "error": str(e)})

    suffix = Path(audio.filename or "audio").suffix or ".wav"
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        tmp.write(contents)
        tmp_path = tmp.name

    try:
        result = run_transcription(tmp_path, audio.filename or "audio", language, model)
        return TranscriptionResponse(success=True, **result)
    except TranscriptionError as e:
        return JSONResponse(status_code=422, content={"success": False, "error": str(e)})
    except Exception:
        # Never leak internal stack traces to the caller.
        logger.exception("Unexpected error transcribing '%s'", audio.filename)
        return JSONResponse(status_code=500, content={"success": False, "error": "Unable to process audio."})
    finally:
        Path(tmp_path).unlink(missing_ok=True)
