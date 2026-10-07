from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv


BASE_DIR = Path(__file__).resolve().parents[1]
load_dotenv(BASE_DIR / ".env")


def _int_env(name: str, default: int) -> int:
    value = os.getenv(name)

    if value is None or value.strip() == "":
        return default

    try:
        return int(value)
    except ValueError:
        return default


def _bool_env(name: str, default: bool = False) -> bool:
    value = os.getenv(name)

    if value is None:
        return default

    return value.strip().lower() in {"1", "true", "yes", "on"}


class Settings:
    service_name: str = os.getenv("OCR_SERVICE_NAME", "PaperTrail OCR Service")
    host: str = os.getenv("OCR_HOST", "127.0.0.1")
    port: int = _int_env("OCR_PORT", 8001)
    ocr_language: str = os.getenv("OCR_LANGUAGE", "en")
    upload_dir: Path = Path(os.getenv("OCR_UPLOAD_DIR", str(BASE_DIR / "uploads")))
    pdf_dpi: int = _int_env("OCR_PDF_DPI", 200)
    max_file_mb: int = _int_env("OCR_MAX_FILE_MB", 20)
    keep_temp_files: bool = _bool_env("OCR_KEEP_TEMP_FILES", False)
    allowed_origins: list[str] = [
        origin.strip()
        for origin in os.getenv("OCR_ALLOWED_ORIGINS", "*").split(",")
        if origin.strip()
    ]
    allowed_extensions: tuple[str, ...] = tuple(
        extension.strip().lower().lstrip(".")
        for extension in os.getenv("OCR_ALLOWED_EXTENSIONS", "jpg,jpeg,png,pdf").split(",")
        if extension.strip()
    )

    @property
    def max_file_bytes(self) -> int:
        return self.max_file_mb * 1024 * 1024


settings = Settings()

