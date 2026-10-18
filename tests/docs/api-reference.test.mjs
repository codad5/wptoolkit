// The API reference (docs/api, ADR-0028) read the way documentation tooling reads it: with gray-matter.
// PHP generates the files; this proves that real front-matter tools can follow them — every page
// parses, carries the documented fields, and previous / next / up lead where they say.
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import matter from 'gray-matter';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../docs/api');
const SCHEMA = 'wptoolkit-api/1';
const TYPES = ['class', 'interface', 'enum', 'trait', 'namespace', 'index'];

function markdownFiles(directory) {
    return readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);
        return statSync(path).isDirectory() ? markdownFiles(path) : path.endsWith('.md') ? [path] : [];
    });
}

const pages = markdownFiles(root).map((file) => {
    const parsed = matter(readFileSync(file, 'utf8'));
    return { file, path: relative(root, file).split(sep).join('/'), data: parsed.data, body: parsed.content };
});
const byPath = new Map(pages.map((page) => [page.path, page]));
const ordered = [...pages].sort((a, b) => a.data.order - b.data.order);

test('there are pages to read', () => {
    assert.ok(pages.length > 10, `only ${pages.length} pages`);
    assert.ok(byPath.has('index.md'));
});

test('every page declares the schema and the fields its type requires', () => {
    for (const { path, data } of pages) {
        assert.equal(data.schema, SCHEMA, `${path}: schema`);
        assert.ok(TYPES.includes(data.type), `${path}: type "${data.type}"`);
        assert.equal(typeof data.title, 'string', `${path}: title`);
        assert.ok(data.title.length > 0, `${path}: title is empty`);
        assert.equal(typeof data.summary, 'string', `${path}: summary`);
        assert.ok(Number.isInteger(data.order), `${path}: order must be an integer (it parsed as ${typeof data.order})`);

        if (!['namespace', 'index'].includes(data.type)) {
            for (const field of ['fqcn', 'namespace', 'layer', 'source', 'since', 'stability']) {
                assert.equal(typeof data[field], 'string', `${path}: ${field}`);
                assert.ok(data[field].length > 0, `${path}: ${field} is empty`);
            }
            assert.ok(['stable', 'deprecated'].includes(data.stability), `${path}: stability`);
            assert.match(data.since, /^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/, `${path}: since is a version, and a string`);
            assert.ok(data.fqcn.startsWith(`${data.namespace}\\`), `${path}: fqcn belongs to its namespace`);
            assert.ok(existsSync(resolve(root, '../..', data.source)), `${path}: source ${data.source} exists`);
        }
        if (data.related !== undefined) {
            assert.ok(Array.isArray(data.related.adr), `${path}: related.adr is a list`);
            assert.ok(data.related.adr.every(Number.isInteger), `${path}: related.adr holds numbers`);
        }
    }
});

test('orders are unique and run from 0 with no gaps', () => {
    assert.deepEqual(ordered.map((page) => page.data.order), ordered.map((_, i) => i));
});

test('previous, next and up are links that work from the page they are on', () => {
    ordered.forEach((page, i) => {
        const here = dirname(page.file);
        for (const [name, expected] of [['prev', ordered[i - 1]], ['next', ordered[i + 1]]]) {
            const link = page.data[name];
            if (expected === undefined) {
                assert.equal(link, undefined, `${page.path}: has no ${name}`);
                continue;
            }
            assert.ok(link, `${page.path}: missing ${name}`);
            assert.equal(link.title, expected.data.title, `${page.path}: ${name} title`);
            assert.equal(resolve(here, link.path), expected.file, `${page.path}: ${name} path leads to ${expected.path}`);
            assert.ok(page.body.includes(`](${link.path})`), `${page.path}: the footer links ${name} too`);
        }
        if (page.data.up !== undefined) {
            const up = byPath.get(relative(root, resolve(here, page.data.up.path)).split(sep).join('/'));
            assert.ok(up, `${page.path}: up leads to a page`);
            assert.equal(up.data.title, page.data.up.title, `${page.path}: up title`);
        }
    });
});

test('every relative link in a page body points at a file that exists', () => {
    for (const page of pages) {
        for (const [, target] of page.body.matchAll(/\]\((?!https?:|#)([^)#\s]+)(?:#[^)]*)?\)/g)) {
            assert.ok(existsSync(resolve(dirname(page.file), target)), `${page.path}: broken link ${target}`);
        }
    }
});

test('index.json agrees with the front matter and joins up', () => {
    const index = JSON.parse(readFileSync(join(root, 'index.json'), 'utf8'));
    assert.equal(index.schema, SCHEMA);
    assert.equal(index.pages.length, pages.length, 'every page is indexed');

    index.pages.forEach((entry, i) => {
        const page = byPath.get(entry.path);
        assert.ok(page, `${entry.path} exists`);
        assert.equal(entry.order, i);
        assert.equal(entry.order, page.data.order, `${entry.path}: order`);
        assert.equal(entry.title, page.data.title, `${entry.path}: title`);
        assert.equal(entry.summary, page.data.summary, `${entry.path}: summary`);
        assert.equal(entry.fqcn ?? undefined, page.data.fqcn, `${entry.path}: fqcn`);
        assert.equal(entry.prev, index.pages[i - 1]?.path ?? null, `${entry.path}: prev joins`);
        assert.equal(entry.next, index.pages[i + 1]?.path ?? null, `${entry.path}: next joins`);
        if (entry.up !== null) {
            assert.ok(byPath.has(entry.up), `${entry.path}: up ${entry.up} is a page`);
        }
    });
});

test('class names are unique, and every documented class is reachable by following next', () => {
    const names = pages.filter((page) => page.data.fqcn).map((page) => page.data.fqcn);
    assert.equal(new Set(names).size, names.length, 'duplicate fqcn');

    const seen = new Set();
    let page = byPath.get('index.md');
    while (page) {
        assert.ok(!seen.has(page.path), `loop at ${page.path}`);
        seen.add(page.path);
        page = page.data.next ? byPath.get(relative(root, resolve(dirname(page.file), page.data.next.path)).split(sep).join('/')) : undefined;
    }
    assert.equal(seen.size, pages.length, 'following next from the index visits every page');
});
