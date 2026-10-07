document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('signatureSetupForm');
    const canvas = document.getElementById('signatureCanvas');
    const clearButton = document.getElementById('clearSignatureCanvasBtn');
    const saveButton = document.getElementById('saveSignatureCanvasBtn');
    const drawnInput = document.getElementById('drawnSignatureDataInput');
    const styleInput = document.getElementById('signatureStyleInput');
    const typedNameInput = document.getElementById('typed_signature_name');
    const typedPreview = document.querySelector('[data-typed-signature-preview]');
    const imageInput = document.getElementById('signatureImageInput');
    const imagePreview = imageInput?.closest('[data-signature-panel]')?.querySelector('.signature-preview');
    const canvasStatus = document.querySelector('[data-signature-canvas-status]');
    const tabs = Array.from(document.querySelectorAll('[data-signature-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-signature-panel]'));

    if (!form) {
        return;
    }

    const activePanel = (name) => panels.find((panel) => panel.dataset.signaturePanel === name);
    const isVisible = (element) => Boolean(element?.offsetParent || element?.getClientRects().length);

    const setCanvasStatus = (message) => {
        if (canvasStatus) {
            canvasStatus.textContent = message;
        }
    };

    typedNameInput?.addEventListener('input', () => {
        if (typedPreview) {
            typedPreview.textContent = typedNameInput.value.trim() || 'Typed signature preview';
        }
    });

    imageInput?.addEventListener('change', () => {
        const file = imageInput.files?.[0];

        if (styleInput) {
            styleInput.value = 'uploaded';
        }

        if (!file || !imagePreview) {
            return;
        }

        const reader = new FileReader();

        reader.addEventListener('load', () => {
            imagePreview.innerHTML = '';
            const image = document.createElement('img');
            image.src = String(reader.result || '');
            image.alt = 'Selected signature preview';
            imagePreview.appendChild(image);
        });

        reader.readAsDataURL(file);
    });

    if (!canvas || !drawnInput) {
        return;
    }

    const context = canvas.getContext('2d');
    const strokes = [];
    let currentStroke = null;
    let existingSignatureImage = null;
    let hasDrawn = false;
    let canvasReady = false;
    let resizeTimer = null;

    const drawBackground = () => {
        const rect = canvas.getBoundingClientRect();

        context.save();
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.restore();

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, rect.width, rect.height);

        if (existingSignatureImage) {
            const imageRatio = existingSignatureImage.width / Math.max(existingSignatureImage.height, 1);
            const maxWidth = rect.width * 0.84;
            const maxHeight = rect.height * 0.72;
            let drawWidth = maxWidth;
            let drawHeight = drawWidth / imageRatio;

            if (drawHeight > maxHeight) {
                drawHeight = maxHeight;
                drawWidth = drawHeight * imageRatio;
            }

            context.drawImage(
                existingSignatureImage,
                (rect.width - drawWidth) / 2,
                (rect.height - drawHeight) / 2,
                drawWidth,
                drawHeight
            );
        }
    };

    const drawStroke = (stroke) => {
        if (!stroke?.length) {
            return;
        }

        context.beginPath();
        context.moveTo(stroke[0].x, stroke[0].y);

        if (stroke.length === 1) {
            context.lineTo(stroke[0].x + 0.01, stroke[0].y + 0.01);
        } else {
            stroke.slice(1).forEach((point) => context.lineTo(point.x, point.y));
        }

        context.stroke();
        context.closePath();
    };

    const redrawCanvas = () => {
        const rect = canvas.getBoundingClientRect();

        if (!rect.width || !rect.height) {
            return;
        }

        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#0f172a';
        context.lineWidth = 2.25;

        drawBackground();
        strokes.forEach(drawStroke);
    };

    const resizeCanvas = () => {
        const rect = canvas.getBoundingClientRect();

        if (!rect.width || !rect.height || !isVisible(canvas)) {
            canvasReady = false;
            return false;
        }

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = Math.max(1, Math.round(rect.width * ratio));
        canvas.height = Math.max(1, Math.round(rect.height * ratio));
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        canvasReady = true;
        redrawCanvas();

        return true;
    };

    const ensureCanvasReady = () => {
        window.requestAnimationFrame(() => {
            if (!resizeCanvas()) {
                window.setTimeout(resizeCanvas, 80);
            }
        });
    };

    const pointFromEvent = (event) => {
        const rect = canvas.getBoundingClientRect();

        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    };

    const updateDrawnValue = () => {
        if (!canvasReady || (!hasDrawn && !existingSignatureImage)) {
            drawnInput.value = '';
            return;
        }

        drawnInput.value = canvas.toDataURL('image/png');
    };

    const showSignatureRequired = () => {
        if (window.PaperTrailDialog?.notice) {
            window.PaperTrailDialog.notice('Please draw a signature before saving the drawing.', {
                title: 'Signature Required',
            });
        } else {
            window.alert('Please draw a signature before saving the drawing.');
        }
    };

    const startDrawing = (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        if (!canvasReady) {
            ensureCanvasReady();
        }

        event.preventDefault();
        canvas.setPointerCapture?.(event.pointerId);

        currentStroke = [pointFromEvent(event)];
        strokes.push(currentStroke);
        hasDrawn = true;

        if (styleInput) {
            styleInput.value = 'drawn';
        }

        setCanvasStatus('Drawing...');
        redrawCanvas();
    };

    const draw = (event) => {
        if (!currentStroke) {
            return;
        }

        event.preventDefault();
        currentStroke.push(pointFromEvent(event));
        redrawCanvas();
    };

    const stopDrawing = (event) => {
        if (!currentStroke) {
            return;
        }

        event.preventDefault();
        canvas.releasePointerCapture?.(event.pointerId);
        currentStroke = null;
        updateDrawnValue();
        setCanvasStatus('Drawing ready to save.');
    };

    const clearCanvas = () => {
        strokes.length = 0;
        currentStroke = null;
        existingSignatureImage = null;
        hasDrawn = false;
        drawnInput.value = '';
        redrawCanvas();
        setCanvasStatus('Canvas cleared. Draw your new signature.');
    };

    const saveDrawing = () => {
        if (!canvasReady) {
            ensureCanvasReady();
        }

        if (!hasDrawn && !existingSignatureImage) {
            showSignatureRequired();
            return;
        }

        if (styleInput) {
            styleInput.value = 'drawn';
        }

        updateDrawnValue();
        setCanvasStatus('Drawing saved. Click Save Signature Profile to apply it.');

        if (saveButton) {
            saveButton.textContent = 'Drawing Saved';
            window.setTimeout(() => {
                saveButton.textContent = 'Save Drawing';
            }, 1400);
        }
    };

    const loadExistingDrawnSignature = () => {
        const source = canvas.dataset.existingSignatureSrc;

        if (!source) {
            ensureCanvasReady();
            return;
        }

        const image = new Image();
        image.addEventListener('load', () => {
            existingSignatureImage = image;
            ensureCanvasReady();
            setCanvasStatus('Current drawn signature loaded.');
        });
        image.addEventListener('error', ensureCanvasReady);
        image.src = source;
    };

    tabs.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.signatureTab;

            tabs.forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.signaturePanel === target);
            });

            if (styleInput) {
                styleInput.value = target;
            }

            if (target !== 'drawn') {
                drawnInput.value = '';
                currentStroke = null;
                setCanvasStatus('');
            } else {
                ensureCanvasReady();
            }
        });
    });

    clearButton?.addEventListener('click', clearCanvas);
    saveButton?.addEventListener('click', saveDrawing);

    canvas.addEventListener('pointerdown', startDrawing);
    canvas.addEventListener('pointermove', draw);
    canvas.addEventListener('pointerup', stopDrawing);
    canvas.addEventListener('pointercancel', stopDrawing);
    canvas.addEventListener('pointerleave', stopDrawing);

    form.addEventListener('submit', (event) => {
        if (styleInput?.value === 'drawn' && !hasDrawn && !existingSignatureImage) {
            event.preventDefault();
            showSignatureRequired();
            ensureCanvasReady();
            return;
        }

        if (styleInput?.value === 'drawn' && (hasDrawn || existingSignatureImage)) {
            updateDrawnValue();
        }
    });

    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            if (isVisible(activePanel('drawn'))) {
                resizeCanvas();
            }
        }, 120);
    });

    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(() => {
            if (isVisible(activePanel('drawn'))) {
                resizeCanvas();
            }
        });
        observer.observe(canvas);
    }

    loadExistingDrawnSignature();

    if (isVisible(activePanel('drawn'))) {
        ensureCanvasReady();
    }
});
