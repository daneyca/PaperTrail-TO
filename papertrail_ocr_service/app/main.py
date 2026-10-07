from __future__ import annotations

import logging
import uuid
from pathlib import Path

from fastapi import FastAPI, File, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app.config import settings
from app.ocr_service import OcrInputError, OcrProcessingError, ocr_service
from app.schemas import HealthResponse, OcrResponse


logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

settings.upload_dir.mkdir(parents=True, exist_ok=True)

app = FastAPI(
    title=settings.service_name,
    description="Standalone OCR API for PaperTrail procurement document images and PDFs.",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.get("/", response_model=HealthResponse)
def root() -> HealthResponse:
    return HealthResponse(
        service=settings.service_name,
        status="running",
        language=settings.ocr_language,
    )


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return root()


@app.post("/ocr/extract", response_model=OcrResponse)
async def extract_ocr(file: UploadFile = File(...)) -> OcrResponse | JSONResponse:
    original_filename = Path(file.filename or "").name

    if not original_filename:
        return _error_response(400, None, "Invalid file")

    extension = original_filename.rsplit(".", 1)[-1].lower() if "." in original_filename else ""

    if extension not in settings.allowed_extensions:
        return _error_response(400, original_filename, "Unsupported file type")

    data = await file.read()

    if not data:
        return _error_response(400, original_filename, "Invalid file")

    if len(data) > settings.max_file_bytes:
        return _error_response(400, original_filename, f"File exceeds {settings.max_file_mb} MB limit")

    temp_path = settings.upload_dir / f"{uuid.uuid4()}.{extension}"

    try:
        temp_path.write_bytes(data)
        result = ocr_service.extract_text(temp_path)

        return OcrResponse(
            success=True,
            filename=original_filename,
            text=result.text,
            confidence=result.average_confidence,
            details=result.results,
        )
    except OcrInputError:
        logger.warning("Invalid OCR upload rejected: %s", original_filename)
        return _error_response(400, original_filename, "Invalid file")
    except OcrProcessingError:
        logger.exception("OCR processing failed for %s.", original_filename)
        return _error_response(500, original_filename, "OCR processing failed")
    except Exception:
        logger.exception("Unexpected OCR service error for %s.", original_filename)
        return _error_response(500, original_filename, "OCR processing failed")
    finally:
        await file.close()

        if not settings.keep_temp_files:
            temp_path.unlink(missing_ok=True)


def _error_response(status_code: int, filename: str | None, message: str) -> JSONResponse:
    response = OcrResponse(
        success=False,
        filename=filename,
        message=message,
    )

    payload = response.model_dump() if hasattr(response, "model_dump") else response.dict()

    return JSONResponse(status_code=status_code, content=payload)

