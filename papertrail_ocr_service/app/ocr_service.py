from __future__ import annotations

import logging
import statistics
import tempfile
from pathlib import Path
from typing import Any

from app.config import settings
from app.schemas import OcrDetail, OcrInternalResult


logger = logging.getLogger(__name__)


class OcrInputError(Exception):
    """Raised when an uploaded file cannot be processed as an OCR input."""


class OcrProcessingError(Exception):
    """Raised when OCR processing fails after input validation."""


class PaddleOcrService:
    def __init__(self) -> None:
        self._ocr: Any = None

    @property
    def ocr(self) -> Any:
        if self._ocr is None:
            self._ocr = self._create_ocr_engine()

        return self._ocr

    def extract_text(self, file_path: Path | str) -> OcrInternalResult:
        path = Path(file_path)

        if not path.exists() or not path.is_file():
            raise OcrInputError("Invalid file")

        extension = path.suffix.lower().lstrip(".")

        if extension not in settings.allowed_extensions:
            raise OcrInputError("Unsupported file type")

        if extension == "pdf":
            details = self._extract_pdf(path)
        else:
            details = self._extract_image(path, page=1)

        text = "\n".join(detail.text for detail in details if detail.text.strip())
        confidences = [detail.confidence for detail in details if detail.confidence >= 0]
        average_confidence = round(statistics.mean(confidences), 4) if confidences else 0.0

        return OcrInternalResult(
            text=text,
            results=details,
            average_confidence=average_confidence,
        )

    def _create_ocr_engine(self) -> Any:
        try:
            from paddleocr import PaddleOCR
        except Exception as exc:  # pragma: no cover - depends on local install
            logger.exception("PaddleOCR import failed.")
            raise OcrProcessingError("OCR engine is not installed") from exc

        engine_configs = [
            {"use_angle_cls": True, "lang": settings.ocr_language, "show_log": False},
            {"lang": settings.ocr_language},
        ]

        for config in engine_configs:
            try:
                return PaddleOCR(**config)
            except TypeError:
                continue
            except Exception as exc:  # pragma: no cover - depends on model download/runtime
                logger.exception("PaddleOCR initialization failed.")
                raise OcrProcessingError("OCR engine failed to initialize") from exc

        raise OcrProcessingError("OCR engine failed to initialize")

    def _extract_pdf(self, path: Path) -> list[OcrDetail]:
        try:
            from pdf2image import convert_from_path
        except Exception as exc:  # pragma: no cover - depends on local install
            logger.exception("pdf2image import failed.")
            raise OcrProcessingError("PDF support is not installed") from exc

        page_paths: list[Path] = []

        try:
            pages = convert_from_path(str(path), dpi=settings.pdf_dpi)
        except Exception as exc:
            logger.exception("PDF conversion failed.")
            raise OcrProcessingError("PDF conversion failed") from exc

        details: list[OcrDetail] = []

        try:
            for index, page in enumerate(pages, start=1):
                with tempfile.NamedTemporaryFile(
                    suffix=f"-page-{index}.png",
                    dir=settings.upload_dir,
                    delete=False,
                ) as temp_file:
                    page_path = Path(temp_file.name)

                page.save(page_path, "PNG")
                page_paths.append(page_path)
                details.extend(self._extract_image(page_path, page=index))
        finally:
            if not settings.keep_temp_files:
                for page_path in page_paths:
                    page_path.unlink(missing_ok=True)

        return details

    def _extract_image(self, path: Path, page: int | None = None) -> list[OcrDetail]:
        try:
            raw_result = self._run_paddle(path)
        except Exception as exc:
            logger.exception("Image OCR failed.")
            raise OcrProcessingError("Image OCR failed") from exc

        return self._parse_paddle_result(raw_result, page=page)

    def _run_paddle(self, path: Path) -> Any:
        engine = self.ocr

        if hasattr(engine, "ocr"):
            try:
                return engine.ocr(str(path), cls=True)
            except TypeError:
                return engine.ocr(str(path))

        if hasattr(engine, "predict"):
            return engine.predict(str(path))

        raise OcrProcessingError("OCR engine does not expose a supported API")

    def _parse_paddle_result(self, raw_result: Any, page: int | None = None) -> list[OcrDetail]:
        details: list[OcrDetail] = []

        def add_detail(text: Any, confidence: Any, detail_page: int | None = page) -> None:
            text_value = str(text or "").strip()

            if not text_value:
                return

            details.append(
                OcrDetail(
                    text=text_value,
                    confidence=self._normalize_confidence(confidence),
                    page=detail_page,
                )
            )

        def walk(node: Any) -> None:
            if node is None:
                return

            if isinstance(node, dict):
                texts = node.get("rec_texts") or node.get("texts")
                scores = node.get("rec_scores") or node.get("scores") or node.get("confidences")

                if isinstance(texts, list):
                    for index, text in enumerate(texts):
                        score = scores[index] if isinstance(scores, list) and index < len(scores) else 0
                        add_detail(text, score)
                    return

                if "text" in node:
                    add_detail(node.get("text"), node.get("confidence", node.get("score", 0)))
                    return

                for value in node.values():
                    walk(value)
                return

            if isinstance(node, (tuple, list)):
                if len(node) >= 2 and isinstance(node[0], str) and self._is_number(node[1]):
                    add_detail(node[0], node[1])
                    return

                if (
                    len(node) >= 2
                    and isinstance(node[1], (tuple, list))
                    and len(node[1]) >= 2
                    and isinstance(node[1][0], str)
                ):
                    add_detail(node[1][0], node[1][1])
                    return

                for item in node:
                    walk(item)

        walk(raw_result)

        return details

    def _normalize_confidence(self, value: Any) -> float:
        try:
            confidence = float(value)
        except (TypeError, ValueError):
            return 0.0

        if confidence > 1:
            confidence = confidence / 100

        return round(max(0.0, min(confidence, 1.0)), 4)

    def _is_number(self, value: Any) -> bool:
        return isinstance(value, (int, float)) and not isinstance(value, bool)


ocr_service = PaddleOcrService()

