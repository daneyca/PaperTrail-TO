from __future__ import annotations

import argparse
from pathlib import Path

import requests


def main() -> None:
    parser = argparse.ArgumentParser(description="Send a sample document to the PaperTrail OCR service.")
    parser.add_argument("file", help="Path to a JPG, JPEG, PNG, or PDF file.")
    parser.add_argument(
        "--url",
        default="http://127.0.0.1:8001/ocr/extract",
        help="OCR endpoint URL.",
    )

    args = parser.parse_args()
    file_path = Path(args.file)

    if not file_path.exists() or not file_path.is_file():
        raise SystemExit(f"File not found: {file_path}")

    with file_path.open("rb") as file_handle:
        response = requests.post(
            args.url,
            files={"file": (file_path.name, file_handle)},
            timeout=120,
        )

    payload = response.json()
    print(f"HTTP status: {response.status_code}")
    print(f"Success: {payload.get('success')}")

    if not payload.get("success"):
        print(f"Message: {payload.get('message')}")
        return

    print(f"Filename: {payload.get('filename')}")
    print(f"Confidence: {payload.get('confidence')}")
    print("Extracted text:")
    print(payload.get("text") or "")


if __name__ == "__main__":
    main()

