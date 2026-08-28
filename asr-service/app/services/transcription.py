import logging
import subprocess
import tempfile
import time
from pathlib import Path

import torch
import torchaudio

from app.config import get_settings
from app.models.model_manager import get_model_manager

logger = logging.getLogger("asr.transcription")

SUPPORTED_EXTENSIONS = {".wav", ".mp3", ".ogg", ".m4a"}
TARGET_SAMPLE_RATE = 16000


class TranscriptionError(Exception):
    """Raised for any expected failure — validation, config, or processing."""


def validate_upload(filename: str, size_bytes: int) -> None:
    settings = get_settings()
    ext = Path(filename).suffix.lower()

    if ext not in SUPPORTED_EXTENSIONS:
        raise TranscriptionError(
            f"Unsupported audio format '{ext}'. Supported: {', '.join(sorted(SUPPORTED_EXTENSIONS))}."
        )

    max_bytes = settings.max_audio_size_mb * 1024 * 1024
    if size_bytes > max_bytes:
        raise TranscriptionError(f"Audio file exceeds the {settings.max_audio_size_mb}MB limit.")

    if size_bytes == 0:
        raise TranscriptionError("Audio file is empty.")


def normalize_audio(src_path: str) -> str:
    """Convert arbitrary input audio to mono 16kHz PCM WAV via ffmpeg.

    Yeastar recordings aren't guaranteed to share one codec/sample-rate, so
    every file is normalized before it reaches the model.
    """
    fd, dst_path = tempfile.mkstemp(suffix=".wav")
    import os
    os.close(fd)

    settings = get_settings()
    result = subprocess.run(
        [
            settings.ffmpeg_path, "-y", "-i", src_path,
            "-ac", "1", "-ar", str(TARGET_SAMPLE_RATE), "-f", "wav", dst_path,
        ],
        capture_output=True,
        text=True,
    )

    if result.returncode != 0:
        logger.error("ffmpeg normalization failed: %s", result.stderr[-500:])
        raise TranscriptionError("Unable to process audio (invalid or corrupt file).")

    return dst_path


def resolve_model_name(language: str, model_override: str | None) -> str:
    if model_override:
        return model_override

    settings = get_settings()
    configured = settings.models_by_language().get(language)

    if not configured:
        raise TranscriptionError(
            f"No ASR model configured for language '{language}'. "
            f"Set the corresponding ASR_MODEL_* environment variable — "
            f"do not silently fall back to another language's model."
        )

    return configured


def transcribe(audio_path: str, filename: str, language: str, model_override: str | None = None) -> dict:
    settings = get_settings()
    language = language.lower()

    if language not in settings.language_codes():
        raise TranscriptionError(
            f"Unknown language '{language}'. Expected one of: {', '.join(settings.language_codes())}."
        )

    model_name = resolve_model_name(language, model_override)

    normalized_path = normalize_audio(audio_path)
    try:
        waveform, sample_rate = torchaudio.load(normalized_path)
        if waveform.shape[0] > 1:
            waveform = waveform.mean(dim=0, keepdim=True)
        if sample_rate != TARGET_SAMPLE_RATE:
            waveform = torchaudio.functional.resample(waveform, sample_rate, TARGET_SAMPLE_RATE)

        audio_array = waveform.squeeze(0).numpy()
        if audio_array.size == 0:
            raise TranscriptionError("Audio contains no decodable samples.")

        # Model load (incl. first-time download) happens once and is cached —
        # excluded from processing_time_ms so the metric reflects actual
        # inference time, not the one-off download/load cost.
        processor, model = get_model_manager().get(model_name)

        started = time.perf_counter()

        inputs = processor(audio_array, sampling_rate=TARGET_SAMPLE_RATE, return_tensors="pt")
        inputs = {k: v.to(settings.device) for k, v in inputs.items()}

        with torch.no_grad():
            logits = model(**inputs).logits

        predicted_ids = torch.argmax(logits, dim=-1)
        transcript = processor.batch_decode(predicted_ids)[0].strip()

        # Confidence proxy: mean of the per-frame max softmax probability.
        # This is NOT a calibrated likelihood — treat it as a rough signal only.
        probs = torch.softmax(logits, dim=-1)
        confidence = float(probs.max(dim=-1).values.mean().item())

        processing_time_ms = int((time.perf_counter() - started) * 1000)

        if not transcript:
            raise TranscriptionError("Transcription produced empty output.")

        return {
            "language": language,
            "language_code": settings.language_codes()[language],
            "transcript": transcript,
            "confidence": round(confidence, 4),
            "model": model_name,
            "processing_time_ms": processing_time_ms,
        }
    finally:
        Path(normalized_path).unlink(missing_ok=True)
