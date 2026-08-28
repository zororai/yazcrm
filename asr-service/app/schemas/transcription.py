from pydantic import BaseModel


class TranscriptionResponse(BaseModel):
    success: bool
    language: str
    language_code: str
    transcript: str
    confidence: float | None = None
    model: str
    processing_time_ms: int


class ErrorResponse(BaseModel):
    success: bool = False
    error: str


class ModelInfo(BaseModel):
    language: str
    model: str
    enabled: bool


class ModelsResponse(BaseModel):
    models: list[ModelInfo]


class HealthResponse(BaseModel):
    status: str
