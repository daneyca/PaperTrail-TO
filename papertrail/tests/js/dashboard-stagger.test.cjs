const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/dashboard-stagger.js'), 'utf8');

class Element {
    constructor(tag, id = '', classes = [], children = [], options = {}) {
        this.tag = tag;
        this.id = id;
        this.children = children;
        this.hidden = options.hidden ?? false;
        this.attributes = options.attributes ?? {};
        this.display = options.display ?? 'block';
        this.classes = new Set(classes);
        this.properties = new Map();
        this.classList = {
            add: (name) => this.classes.add(name),
            [Symbol.iterator]: () => this.classes[Symbol.iterator](),
        };
        this.style = { setProperty: (name, value) => this.properties.set(name, value) };
    }

    matches(selector) {
        return selector.split(',').some((part) => {
            const value = part.trim();
            if (value.startsWith('.')) return this.classes.has(value.slice(1));
            if (value === '[aria-hidden="true"]') return this.attributes['aria-hidden'] === 'true';
            if (value === '[role="dialog"]') return this.attributes.role === 'dialog';
            return this.tag === value;
        });
    }
}

function run(root, reducedMotion = false) {
    vm.runInNewContext(script, {
        document: { getElementById: () => root },
        HTMLElement: Element,
        window: {
            matchMedia: () => ({ matches: reducedMotion }),
            getComputedStyle: (element) => ({ display: element.display, visibility: 'visible' }),
        },
    });
}

function animated(root) {
    const result = [];
    const visit = (element) => {
        if (element.classes.has('pt-stagger-item')) result.push(element);
        element.children.forEach(visit);
    };
    visit(root);
    return result;
}

test('reveals dashboard sections and summary cards separately', () => {
    const hiddenNotice = new Element('div', 'hiddenNotice', [], [], { hidden: true });
    const hero = new Element('section', 'hero');
    const first = new Element('div', 'first');
    const second = new Element('div', 'second');
    const stats = new Element('section', 'stats', ['stat-grid'], [first, second]);
    const table = new Element('section', 'table', ['table-panel']);
    const root = new Element('div', 'dashboardContent', [], [hiddenNotice, hero, stats, table]);

    run(root);

    const items = animated(root);
    assert.deepEqual(items.map((item) => item.id), ['hero', 'first', 'second', 'table']);
    const delays = items.map((item) => Number.parseInt(item.properties.get('--pt-stagger-delay'), 10));
    assert.deepEqual(delays, [...delays].sort((a, b) => a - b));
    assert.ok(delays.at(-1) <= 250);
});

test('unwraps a single purchase request workspace and its split panes', () => {
    const heading = new Element('section', 'heading');
    const drafts = new Element('aside', 'drafts');
    const items = new Element('section', 'items');
    const split = new Element('div', 'split', ['pr-create-split-layout'], [drafts, items]);
    const workspace = new Element('section', 'workspace', ['pr-workspace'], [heading, split]);
    const root = new Element('div', 'dashboardContent', [], [workspace]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['heading', 'drafts', 'items']);
});

test('reveals shared document source sections in order without animating the outer card', () => {
    const heading = new Element('div', 'heading', ['document-create-source-card__header']);
    const steps = new Element('section', 'steps', ['document-workflow-steps']);
    const drafts = new Element('aside', 'drafts', ['po-create-drafts-pane']);
    const picker = new Element('section', 'picker', ['po-create-source-pane']);
    const split = new Element('div', 'split', ['po-create-split-layout'], [drafts, picker]);
    const body = new Element('div', 'body', ['document-create-source-card__body'], [split]);
    const actions = new Element('div', 'actions', ['document-create-source-card__actions']);
    const source = new Element('section', 'source', ['document-create-source-card'], [heading, steps, body, actions]);
    const form = new Element('div', 'form', ['document-create-form']);
    const flow = new Element('section', 'flow', ['document-create-flow'], [source, form]);
    const root = new Element('div', 'dashboardContent', [], [flow]);

    run(root);

    const items = animated(root);
    assert.deepEqual(items.map((item) => item.id), ['heading', 'steps', 'drafts', 'picker', 'actions']);
    assert.deepEqual(items.map((item) => item.properties.get('--pt-stagger-delay')), ['0ms', '55ms', '110ms', '165ms', '220ms']);

    flow.classes.add('is-document-form-ready');
    for (const item of items) item.classes.delete('pt-stagger-item');
    run(root);
    assert.deepEqual(animated(root), []);
});

test('reveals a shared source picker and draft pane separately', () => {
    const drafts = new Element('aside', 'drafts', ['document-create-recent-pane']);
    const picker = new Element('section', 'picker', ['document-create-picker-pane']);
    const split = new Element('div', 'split', ['document-create-split-layout'], [drafts, picker]);
    const body = new Element('div', 'body', ['document-create-source-card__body'], [split]);
    const source = new Element('section', 'source', ['document-create-source-card'], [
        new Element('div', 'heading', ['document-create-source-card__header']),
        body,
        new Element('div', 'actions', ['document-create-source-card__actions']),
    ]);
    const flow = new Element('section', 'flow', ['document-create-flow'], [source]);
    const root = new Element('div', 'dashboardContent', [], [flow]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['heading', 'drafts', 'picker', 'actions']);
});

test('reveals framed panel filters and results without animating individual rows', () => {
    const rows = new Element('table', 'rows');
    const table = new Element('div', 'table', ['table-scroll'], [rows]);
    const panel = new Element('section', 'panel', ['table-panel'], [
        new Element('form', 'filters'),
        table,
    ]);
    const root = new Element('div', 'dashboardContent', [], [panel]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['filters', 'table']);
});

test('caps stagger time on pages with many major sections', () => {
    const sections = Array.from({ length: 10 }, (_, index) => new Element('section', `section-${index}`));
    const root = new Element('div', 'dashboardContent', [], sections);

    run(root);

    const delays = animated(root).map((item) => Number.parseInt(item.properties.get('--pt-stagger-delay'), 10));
    assert.deepEqual(delays.slice(0, 5), [0, 55, 110, 165, 220]);
    assert.ok(delays.every((delay) => delay <= 250));
});

test('keeps a single framed panel together', () => {
    const panel = new Element('section', 'panel', ['table-panel'], [
        new Element('form', 'filters'),
        new Element('div', 'rows'),
    ]);
    const root = new Element('div', 'dashboardContent', [], [panel]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['panel']);
});

test('reveals panels inside a profile layout separately', () => {
    const details = new Element('article', 'details', ['table-panel']);
    const audit = new Element('article', 'audit', ['table-panel']);
    const column = new Element('div', 'column', ['profile-main-column'], [details, audit]);
    const layout = new Element('section', 'layout', ['profile-layout'], [column]);
    const root = new Element('div', 'dashboardContent', [], [
        new Element('section', 'hero', ['dashboard-hero']),
        layout,
    ]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['hero', 'details', 'audit']);
});

test('reveals form sections but skips hidden content', () => {
    const form = new Element('form', 'form', [], [
        new Element('input', 'token', [], [], { display: 'none' }),
        new Element('fieldset', 'first'),
        new Element('fieldset', 'second'),
        new Element('section', 'inactive', [], [], { display: 'none' }),
    ]);
    const root = new Element('div', 'dashboardContent', [], [form]);

    run(root);

    assert.deepEqual(animated(root).map((item) => item.id), ['first', 'second']);
});

test('does not animate when reduced motion is requested', () => {
    const root = new Element('div', 'dashboardContent', [], [new Element('section', 'hero')]);

    run(root, true);

    assert.equal(animated(root).length, 0);
});
