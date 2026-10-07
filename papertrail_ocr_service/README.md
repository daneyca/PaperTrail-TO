# PaperTrail OCR Service

Standalone OCR microservice for PaperTrail. It uses FastAPI and PaddleOCR to extract text from uploaded procurement document images and PDFs.

This service is independent from the Laravel application. It does not modify the PaperTrail database, document workflows, chatbot, OpenAI integration, or authenticated pages.

## Folder Structure

```text
papertrail_ocr_service/
├── app/
│   ├── __init__.py
│   ├── main.py
│   ├── ocr_service.py
│   ├── schemas.py
│   └── config.py
├── uploads/
│   └── .gitkeep
├── .env.example
├── .gitignore
├── requirements.txt
├── sample_test.py
└── README.md
```

## Setup

Install Python first if it is not available yet. Python 3.10 or 3.11 is recommended for the PaddleOCR stack on Windows.

From the project root:

```powershell
cd C:\laragon\www\PaperTrail\papertrail_ocr_service
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
```

If `paddlepaddle` installation fails on Windows, install the CPU package from the official PaddlePaddle instructions for your Python version, then run `pip install -r requirements.txt` again.

PDF support uses `pdf2image`, which requires Poppler. Install Poppler for Windows and add its `bin` folder to your PATH if scanned PDFs fail to convert.

## Run

```powershell
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

Expected startup output includes:

```text
Application startup complete.
```

Open:

```text
http://127.0.0.1:8001
```

Expected response:

```json
{
  "service": "PaperTrail OCR Service",
  "status": "running",
  "language": "en"
}
```

## OCR Endpoint

```text
POST /ocr/extract
```

Accepts multipart form data:

```text
file: jpg, jpeg, png, or pdf
```

Example with curl:

```powershell
curl.exe -X POST -F "file=@C:\path\to\sample_purchase_request.png" http://127.0.0.1:8001/ocr/extract
```

Successful response:

```json
{
  "success": true,
  "filename": "sample-pr.png",
  "text": "PURCHASE REQUEST\nMunicipality of Tomas Oppus\nOffice Supplies",
  "confidence": 0.94,
  "details": [
    {
      "text": "PURCHASE REQUEST",
      "confidence": 0.98,
      "page": 1
    }
  ],
  "message": null,
  "metadata": {}
}
```

Failed response:

```json
{
  "success": false,
  "filename": "sample.txt",
  "text": "",
  "confidence": 0.0,
  "details": [],
  "message": "Unsupported file type",
  "metadata": {}
}
```

## Test Client

After the service is running:

```powershell
python sample_test.py C:\path\to\sample_purchase_request.png
```

For PDF:

```powershell
python sample_test.py C:\path\to\scanned_purchase_request.pdf
```

## Notes

- Uploaded files are stored only temporarily in `uploads/`.
- Temporary files are deleted after OCR unless `OCR_KEEP_TEMP_FILES=true`.
- The service returns clean JSON for Laravel integration later.
- It does not call OpenAI.
- It does not permanently store documents.
