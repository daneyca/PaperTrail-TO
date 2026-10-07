(function () {
    const actionSelector = [
        '.table-actions a',
        '.table-actions button',
        '.compact-action-row a',
        '.compact-action-row button',
        '.report-row-actions a',
        '.report-row-actions button',
        '.report-hero-actions a',
        '.report-hero-actions button',
        '.report-header-actions a',
        '.report-header-actions button',
        '.hero-actions a',
        '.hero-actions button',
        '.document-action-bar a',
        '.document-action-bar button',
        '.pr-action-toolbar a',
        '.pr-action-toolbar button',
        '.pr-toolbar-group a',
        '.pr-toolbar-group button',
        '.app-toolbar a',
        '.app-toolbar button',
        '.record-actions a',
        '.record-actions button',
        '.document-record-actions a',
        '.document-record-actions button',
        '.document-draft-actions a',
        '.document-draft-actions button',
        '.document-draft-file-actions a',
        '.document-draft-file-actions button',
        '.document-record-icon-btn',
        '.attachment-actions a',
        '.attachment-actions button',
        '.notification-actions a',
        '.notification-actions button',
        '.ai-verification-actions a',
        '.ai-verification-actions button',
        '.signature-actions a',
        '.signature-actions button',
        '.svp-card-actions a',
        '.svp-card-actions button',
        '.hero-actions a.dashboard-action',
        '.hero-actions button.dashboard-action',
        '.icon-action',
        '.notification-icon-action',
        '.attachment-icon-action',
        '.procurement-icon-action',
        '.signature-icon-action',
        '.ai-verification-action',
        '.ai-completeness-button--inline-icon',
        '.ai-route-validation-button--inline-icon',
        '.ai-delay-risk-button--inline-icon',
    ].join(',');

    const tooltipSelector = [
        '.pt-action-button[data-tooltip]',
        '.icon-action[data-tooltip]',
        '.document-record-icon-btn[data-tooltip]',
        '.notification-icon-action[data-tooltip]',
        '.attachment-icon-action[data-tooltip]',
        '.procurement-icon-action[data-tooltip]',
        '.signature-icon-action[data-tooltip]',
        '.ai-verification-action[data-tooltip]',
        '.ai-completeness-button--inline-icon[data-tooltip]',
        '.ai-route-validation-button--inline-icon[data-tooltip]',
        '.ai-delay-risk-button--inline-icon[data-tooltip]',
    ].join(',');

    const icons = {
        view: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3.75 12s2.9-5.25 8.25-5.25S20.25 12 20.25 12 17.35 17.25 12 17.25 3.75 12 3.75 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 14.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" stroke="currentColor" stroke-width="1.8"/></svg>',
        edit: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M4.75 19.25h4.1l10.2-10.2a2.4 2.4 0 0 0-3.4-3.4L5.45 15.85l-.7 3.4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m14.25 7.05 2.7 2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        print: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M7 8V4.75h10V8M7 16.25H5.75A1.75 1.75 0 0 1 4 14.5v-4.25A1.75 1.75 0 0 1 5.75 8.5h12.5A1.75 1.75 0 0 1 20 10.25v4.25a1.75 1.75 0 0 1-1.75 1.75H17" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 13.5h10v5.75H7zM16.5 11.25h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        download: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 4.75v9.5M8.25 10.5 12 14.25l3.75-3.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 15.75v2.5A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25v-2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        open: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M14 4.75h5.25V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.25 4.75 11.5 12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.5 6.25H6.75A1.75 1.75 0 0 0 5 8v9.25A1.75 1.75 0 0 0 6.75 19h9.25a1.75 1.75 0 0 0 1.75-1.75V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        key: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9.75 14.25a4.25 4.25 0 1 1 3-7.25 4.25 4.25 0 0 1-3 7.25Z" stroke="currentColor" stroke-width="1.8"/><path d="m13 11 6.25 6.25M16.25 14.25l-1.75 1.75M18.25 16.25l-1.75 1.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        power: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 3.75v8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M7.05 6.95a7 7 0 1 0 9.9 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M20 6.75 9.5 17.25 4 11.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        return: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M8.25 7.25 4.5 11l3.75 3.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 11h9.5a4.5 4.5 0 0 1 0 9H13" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        delete: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5.25 7.25h13.5M9.25 7.25V5.5a1.25 1.25 0 0 1 1.25-1.25h3A1.25 1.25 0 0 1 14.75 5.5v1.75M7.25 7.25l.8 11a1.75 1.75 0 0 0 1.75 1.62h4.4a1.75 1.75 0 0 0 1.75-1.62l.8-11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.25 11v5M13.75 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        users: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9.5 11.75a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM3.75 19.25a5.75 5.75 0 0 1 11.5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16.25 10.75a2.75 2.75 0 1 0 0-5.5M17.25 19.25h3a4.75 4.75 0 0 0-4.75-4.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        shield: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 21s7-3.25 7-9.25v-5.5L12 3.75l-7 2.5v5.5C5 17.75 12 21 12 21Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m8.75 12.25 2.1 2.1 4.4-4.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        signature: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M4.5 17.5c2.7-5.2 4.7-8 6-8.4.9-.3 1.45.25 1.45 1.15 0 1.65-2.3 3.8-1.25 4.55 1.3.9 3.2-1.35 4.15-.35.55.58.1 1.7-.55 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    };

    const rules = [
        { test: /^view certificate$/i, icon: 'shield', variant: 'icon-action--permissions' },
        { test: /^(review|review \/ accept|review signature|view \/ review|view \/ process|view \/ verify|validate|validation|verify|review (&|and) sign|apply signature|sign\b|sign document)$/i, icon: 'signature', variant: 'icon-action--review' },
        { test: /^(submit\b|approve\b|view \/ approve|accept\b|complete\b|mark complete|confirm\b|view \/ confirm|submit for|acknowledge)/i, icon: 'check', variant: 'icon-action--success' },
        { test: /^view users$/i, icon: 'users', variant: 'icon-action--view' },
        { test: /^(return\b|send back|requires action|view \/ route|route\b|view \/ assign pr no\.?|assign pr no\.?|sign \/ return)/i, icon: 'return', variant: 'icon-action--warning' },
        { test: /^(view|details|view details|view report|view document|view record|view file)\b/i, icon: 'view', variant: 'icon-action--view' },
        { test: /^(open|open document|open record)$/i, icon: 'open', variant: 'icon-action--view' },
        { test: /^(edit|modify|edit \/ revise|revise|continue editing)$/i, icon: 'edit', variant: 'icon-action--edit' },
        { test: /^(print\b|print document|print report)/i, icon: 'print', variant: 'icon-action--print' },
        { test: /^(download\b|download document|download report\b|export\b|export report)/i, icon: 'download', variant: 'icon-action--download' },
        { test: /^reset password$/i, icon: 'key', variant: 'icon-action--password' },
        { test: /^manage permissions$/i, icon: 'shield', variant: 'icon-action--permissions' },
        { test: /^mark as read$/i, icon: 'check', variant: 'icon-action--success' },
        { test: /^activate$/i, icon: 'check', variant: 'icon-action--success' },
        { test: /^(deactivate|disable)$/i, icon: 'power', variant: 'icon-action--danger' },
        { test: /^(delete\b|remove\b|reject\b)/i, icon: 'delete', variant: 'icon-action--danger' },
    ];

    function normalize(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function elementActionText(element) {
        const href = element.getAttribute('href') || element.getAttribute('formaction') || '';
        const classText = typeof element.className === 'string' ? element.className : '';
        const datasetText = [
            element.dataset.tooltip,
            element.dataset.confirmLabel,
            element.dataset.confirmTitle,
            element.dataset.confirmType,
            element.dataset.action,
        ].filter(Boolean).join(' ');

        return normalize([
            element.getAttribute('aria-label'),
            element.getAttribute('title'),
            element.dataset.tooltip,
            element.textContent,
            classText.replace(/[-_]/g, ' '),
            href.replace(/[/?=&_.-]+/g, ' '),
            datasetText,
        ].filter(Boolean).join(' '));
    }

    function explicitActionLabel(element) {
        return normalize(
            element.getAttribute('aria-label')
            || element.getAttribute('title')
            || element.dataset.tooltip
            || element.textContent
        );
    }

    function inferRuleFromActionText(actionText) {
        const text = actionText.toLowerCase();

        if (/\b(delete|destroy|remove|reject|deactivate|disable|danger)\b/.test(text)) {
            return { icon: 'delete', variant: 'icon-action--danger' };
        }

        if (/\b(download|export)\b/.test(text)) {
            return { icon: 'download', variant: 'icon-action--download' };
        }

        if (/\b(print)\b/.test(text)) {
            return { icon: 'print', variant: 'icon-action--print' };
        }

        if (/\b(edit|modify|revise|continue)\b/.test(text)) {
            return { icon: 'edit', variant: 'icon-action--edit' };
        }

        if (/\b(approve|accept|complete|submit|activate|confirm|acknowledge)\b/.test(text)) {
            return { icon: 'check', variant: 'icon-action--success' };
        }

        if (/\b(return|route|assign|warning|requires)\b/.test(text)) {
            return { icon: 'return', variant: 'icon-action--warning' };
        }

        if (/\b(review|validate|validation|verify|signature|sign|permission)\b/.test(text)) {
            return { icon: 'signature', variant: 'icon-action--review' };
        }

        if (/\b(view|details|open|show|certificate)\b/.test(text)) {
            return { icon: 'view', variant: 'icon-action--view' };
        }

        return null;
    }

    function fallbackLabelForRule(rule) {
        if (!rule) {
            return '';
        }

        const labels = {
            view: 'View Details',
            open: 'Open Record',
            edit: 'Edit Record',
            print: 'Print',
            download: 'Download',
            key: 'Reset Password',
            power: 'Deactivate',
            check: 'Confirm Action',
            return: 'Return Document',
            delete: 'Delete Record',
            users: 'View Users',
            shield: 'Manage Permissions',
            signature: 'Review Record',
        };

        return labels[rule.icon] || 'Action';
    }

    function resolveRule(element, label) {
        const explicitRule = label ? rules.find((candidate) => candidate.test.test(label)) : null;
        if (explicitRule) {
            return explicitRule;
        }

        const actionText = elementActionText(element);
        if (!actionText) {
            return null;
        }

        return rules.find((candidate) => candidate.test.test(actionText)) || inferRuleFromActionText(actionText);
    }

    function applyTooltip(element, fallbackLabel) {
        const label = normalize(element.getAttribute('aria-label') || element.getAttribute('title') || element.dataset.tooltip || fallbackLabel);

        if (!label) {
            return;
        }

        element.setAttribute('aria-label', element.getAttribute('aria-label') || label);
        element.setAttribute('title', element.getAttribute('title') || label);
        element.dataset.tooltip = element.dataset.tooltip || label;
    }

    function iconifyAction(element) {
        if (!element) {
            return;
        }

        if (element.dataset.actionIcon === 'false') {
            return;
        }

        if (element.matches('[data-ai-feature-button]')) {
            applyTooltip(element, element.dataset.tooltip || element.getAttribute('aria-label') || element.getAttribute('title'));
            element.dataset.papertrailIconified = 'true';
            return;
        }

        const label = explicitActionLabel(element);
        const rule = resolveRule(element, label);

        if (element.dataset.papertrailIconified === 'true') {
            if (rule) {
                element.classList.add('icon-action', rule.variant);
            }
            applyTooltip(element, label || fallbackLabelForRule(rule) || elementActionText(element));
            return;
        }

        if (rule) {
            element.classList.add('icon-action', rule.variant);
        }

        if (element.querySelector('svg')) {
            applyTooltip(element, label || fallbackLabelForRule(rule) || elementActionText(element));
            element.dataset.papertrailIconified = 'true';
            return;
        }

        if (!label && !rule) {
            return;
        }

        if (!rule) {
            applyTooltip(element, label);
            return;
        }

        applyTooltip(element, label || fallbackLabelForRule(rule) || elementActionText(element));
        element.innerHTML = icons[rule.icon];
        element.dataset.papertrailIconified = 'true';
    }

    function iconifyAll(root) {
        (root || document).querySelectorAll(actionSelector).forEach(iconifyAction);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => iconifyAll(document));
    } else {
        iconifyAll(document);
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                if (node.matches?.(actionSelector)) {
                    iconifyAction(node);
                }

                iconifyAll(node);
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });

    let tooltipEl;
    let activeTooltipTarget;

    function ensureTooltip() {
        if (tooltipEl) {
            return tooltipEl;
        }

        tooltipEl = document.createElement('div');
        tooltipEl.className = 'papertrail-action-tooltip';
        tooltipEl.setAttribute('role', 'tooltip');
        tooltipEl.hidden = true;
        document.body.appendChild(tooltipEl);

        return tooltipEl;
    }

    function restoreNativeTitle(target) {
        if (!target || !target.dataset.nativeTitle) {
            return;
        }

        target.setAttribute('title', target.dataset.nativeTitle);
        delete target.dataset.nativeTitle;
    }

    function positionTooltip() {
        if (!activeTooltipTarget || !tooltipEl || tooltipEl.hidden) {
            return;
        }

        const targetRect = activeTooltipTarget.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect();
        const gap = 10;
        const viewportPadding = 8;
        const fitsAbove = targetRect.top >= tooltipRect.height + gap + viewportPadding;
        const top = fitsAbove
            ? targetRect.top - tooltipRect.height - gap
            : targetRect.bottom + gap;
        const preferredLeft = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        const maxLeft = window.innerWidth - tooltipRect.width - viewportPadding;
        const left = Math.max(viewportPadding, Math.min(preferredLeft, maxLeft));

        tooltipEl.classList.toggle('is-below', !fitsAbove);
        tooltipEl.style.left = `${left}px`;
        tooltipEl.style.top = `${Math.max(viewportPadding, top)}px`;
    }

    function showTooltip(target) {
        const label = normalize(target?.dataset?.tooltip);

        if (!label) {
            return;
        }

        if (activeTooltipTarget && activeTooltipTarget !== target) {
            restoreNativeTitle(activeTooltipTarget);
        }

        activeTooltipTarget = target;

        if (target.hasAttribute('title') && !target.dataset.nativeTitle) {
            target.dataset.nativeTitle = target.getAttribute('title');
            target.removeAttribute('title');
        }

        const tooltip = ensureTooltip();
        tooltip.textContent = label;
        tooltip.hidden = false;
        tooltip.classList.add('is-visible');
        positionTooltip();
    }

    function hideTooltip(target = activeTooltipTarget) {
        restoreNativeTitle(target);

        if (!tooltipEl) {
            activeTooltipTarget = null;
            return;
        }

        tooltipEl.classList.remove('is-visible');
        tooltipEl.hidden = true;
        activeTooltipTarget = null;
    }

    document.addEventListener('pointerover', (event) => {
        const target = event.target.closest?.(tooltipSelector);

        if (target) {
            showTooltip(target);
        }
    });

    document.addEventListener('pointerout', (event) => {
        if (!activeTooltipTarget || activeTooltipTarget.contains(event.relatedTarget)) {
            return;
        }

        hideTooltip(activeTooltipTarget);
    });

    document.addEventListener('focusin', (event) => {
        const target = event.target.closest?.(tooltipSelector);

        if (target) {
            showTooltip(target);
        }
    });

    document.addEventListener('focusout', (event) => {
        if (activeTooltipTarget && activeTooltipTarget === event.target) {
            hideTooltip(activeTooltipTarget);
        }
    });

    window.addEventListener('resize', positionTooltip);
    window.addEventListener('scroll', positionTooltip, true);
})();
