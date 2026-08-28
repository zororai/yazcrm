import logging
import threading

from transformers import AutoModelForCTC, AutoProcessor

from app.config import get_settings

logger = logging.getLogger("asr.model_manager")


class ModelManager:
    """Loads Hugging Face ASR models once and keeps them resident in memory.

    Models are loaded lazily on first use per language, then cached for the
    lifetime of the process — never reloaded per-request.
    """

    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._loaded: dict[str, tuple] = {}  # model_name -> (processor, model)

    def get(self, model_name: str):
        if not model_name:
            raise ValueError("No model configured for this language.")

        if model_name in self._loaded:
            return self._loaded[model_name]

        with self._lock:
            # Re-check after acquiring the lock in case another thread loaded it.
            if model_name in self._loaded:
                return self._loaded[model_name]

            settings = get_settings()
            logger.info("Loading ASR model '%s' onto device '%s'…", model_name, settings.device)

            processor = AutoProcessor.from_pretrained(model_name)
            model = AutoModelForCTC.from_pretrained(model_name)
            model.to(settings.device)
            model.eval()

            self._loaded[model_name] = (processor, model)
            logger.info("Model '%s' loaded and ready.", model_name)

            return self._loaded[model_name]

    def is_loaded(self, model_name: str) -> bool:
        return model_name in self._loaded


_manager: ModelManager | None = None


def get_model_manager() -> ModelManager:
    global _manager
    if _manager is None:
        _manager = ModelManager()
    return _manager
