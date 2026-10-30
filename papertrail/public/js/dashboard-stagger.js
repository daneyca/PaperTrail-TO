(() => {
    const content = document.getElementById('dashboardContent');

    if (!content || content.querySelector('.profile-password-modal.is-open') || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const gridSelector = [
        '.stat-grid',
        '.summary-card-grid',
        '.reports-summary-strip',
        '.notification-summary-strip',
        '.rule-config-summary',
        '.enterprise-kpi-grid',
        '.dashboard-widget-grid',
        '.document-type-card-grid',
    ].join(', ');
    const workspaceSelector = [
        '.enterprise-dashboard',
        '.pr-workspace',
        '.assistant-workspace',
        '.document-form-layout',
        '.pr-create-split-layout',
    ].join(', ');

    const visibleChildren = (parent) => Array.from(parent.children).filter((child) => {
        if (!(child instanceof HTMLElement) || child.hidden || child.matches('[aria-hidden="true"], script, style, template, .sr-only')) {
            return false;
        }

        const style = window.getComputedStyle(child);
        return style.display !== 'none' && style.visibility !== 'hidden';
    });

    const targetsIn = (element, singleRoot = false, depth = 0) => {
        const children = visibleChildren(element);
        if (element.matches('.document-create-flow')) {
            if (element.matches('.is-document-form-ready')) {
                return [];
            }

            const source = children.find((child) => child.matches('.document-create-source-card'));
            if (!source) {
                return [element];
            }

            return visibleChildren(source).flatMap((part) => {
                if (!part.matches('.document-create-source-card__body')) {
                    return [part];
                }

                const groups = visibleChildren(part).flatMap((child) =>
                    child.matches('.document-create-split-layout, .po-create-split-layout')
                        ? visibleChildren(child)
                        : [child]);
                return groups.length > 1 && groups.length <= 6 ? groups : [part];
            });
        }

        const framedGroups = children.filter((child) =>
            child.matches('header, section, article, aside, form, .panel-heading, .table-scroll, .table-responsive, .detail-grid'));
        if (depth < 3 && element.matches('.table-panel')
            && children.length >= 2 && children.length <= 6 && framedGroups.length >= 2) {
            return children.flatMap((child) => targetsIn(child, false, depth + 1));
        }

        const isLayout = Array.from(element.classList).some((name) =>
            name.endsWith('-layout') || name.endsWith('-workspace'));
        const sectionedForm = element.matches('form')
            && children.filter((child) => child.matches('section, article, fieldset')).length > 1;
        const plainWrapper = singleRoot
            && !element.matches('form, .table-panel, .dashboard-hero, .assistant-chat-card, [role="dialog"]');

        if (depth < 3 && isLayout && children.length === 1) {
            return targetsIn(children[0], true, depth + 1);
        }

        if (depth < 3 && children.length > 1 && (
            element.matches(gridSelector)
            || element.matches(workspaceSelector)
            || isLayout
            || sectionedForm
            || plainWrapper
        )) {
            return children.flatMap((child) => targetsIn(child, false, depth + 1));
        }

        return [element];
    };

    const roots = visibleChildren(content);
    const targets = roots.flatMap((root) => targetsIn(root, roots.length === 1));

    targets.forEach((target, index) => {
        const delay = Math.min(index * 55, 250);
        target.style.setProperty('--pt-stagger-delay', `${delay}ms`);
        target.classList.add('pt-stagger-item');
    });
})();
