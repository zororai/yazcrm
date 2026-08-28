from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    asr_service_key: str = "change-this"

    asr_model_shona: str = "badrex/w2v-bert-2.0-shona-asr"
    asr_model_english: str = ""
    asr_model_ndebele: str = ""

    device: str = "cpu"
    max_audio_size_mb: int = 100

    def models_by_language(self) -> dict[str, str]:
        return {
            "shona": self.asr_model_shona,
            "english": self.asr_model_english,
            "ndebele": self.asr_model_ndebele,
        }

    def language_codes(self) -> dict[str, str]:
        return {"shona": "sn", "english": "en", "ndebele": "nd"}


@lru_cache
def get_settings() -> Settings:
    return Settings()
