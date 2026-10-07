from __future__ import annotations

from typing import Any, Optional

from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    service: str
    status: str
    language: str


class OcrDetail(BaseModel):
    text: str
    confidence: float
    page: Optional[int] = None


class OcrInternalResult(BaseModel):
    text: str
    results: list[OcrDetail] = Field(default_factory=list)
    average_confidence: float = 0.0


class OcrResponse(BaseModel):
    success: bool
    filename: Optional[str] = None
    text: str = ""
    confidence: float = 0.0
    details: list[OcrDetail] = Field(default_factory=list)
    message: Optional[str] = None
    metadata: dict[str, Any] = Field(default_factory=dict)

