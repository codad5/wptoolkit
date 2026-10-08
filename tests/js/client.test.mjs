// Tests for resources/js/client.js — run with: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/client.js', import.meta.url), 'utf8');
// The factory is registered under the client's own VERSION; read it so a release bump can't break the tests.
const VERSION = source.match(/var VERSION = '([^']+)';/)[1];

function load(existing = {}) {
    const window = { ...existing };
    vm.runInNewContext(source, { window, globalThis: window, Object, Promise, JSON, Error, encodeURIComponent, Array });
    return window;
}

function fakeFetch(responses) {
    const calls = [];
    const fetch = (url, init) => {
        calls.push({ url, init });
        const next = responses.shift();
        return Promise.resolve({
            ok: next.status >= 200 && next.status < 300,
            status: next.status,
            text: () => Promise.resolve(next.body === undefined ? '' : JSON.stringify(next.body)),
        });
    };
    return { fetch, calls };
}

const config = {
    restUrl: 'https://example.test/wp-json/',
    restNonce: 'rest-nonce',
    ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
    routes: {
        books_id: { methods: ['GET'], path: 'books/{id}', transport: 'rest', namespace: 'my-plugin/v1' },
        books: { methods: ['POST'], path: 'books', transport: 'rest', namespace: 'my-plugin/v1' },
        contact: { methods: ['POST'], path: 'contact', transport: 'ajax', action: 'my_plugin_contact', nonce: 'ajax-nonce' },
    },
};

test('the namespace is locked and the factory is registered under its version', () => {
    const window = load();
    assert.equal(typeof window.wptoolkit.__clients[VERSION], 'function');
    // Strict-mode code gets a TypeError; sloppy-mode page scripts fail silently. Either way it stays.
    assert.throws(() => {
        window.wptoolkit = 'replaced';
    }, TypeError);
    assert.equal(typeof window.wptoolkit, 'object');
});

test('loading twice keeps one factory per version and other entries intact', () => {
    const window = load();
    window.wptoolkit['other-plugin'] = { kept: true };
    vm.runInNewContext(source, { window, globalThis: window, Object, Promise, JSON, Error, encodeURIComponent, Array });
    assert.deepEqual(window.wptoolkit['other-plugin'], { kept: true });
});

test('REST GET fills path parameters and sends the REST nonce', async () => {
    const { fetch, calls } = fakeFetch([{ status: 200, body: { id: 7 } }]);
    const api = load().wptoolkit.__clients[VERSION](config, fetch);

    const data = await api.call('books_id', { id: 7, expand: 1 });

    assert.deepEqual(data, { id: 7 });
    assert.equal(calls[0].url, 'https://example.test/wp-json/my-plugin/v1/books/7?expand=1');
    assert.equal(calls[0].init.headers['X-WP-Nonce'], 'rest-nonce');
});

test('REST POST sends JSON', async () => {
    const { fetch, calls } = fakeFetch([{ status: 201, body: { id: 1 } }]);
    const api = load().wptoolkit.__clients[VERSION](config, fetch);

    await api.call('books', { title: 'Dune' });

    assert.equal(calls[0].init.method, 'POST');
    assert.equal(calls[0].init.body, '{"title":"Dune"}');
});

test('Ajax posts the action and the route nonce as form data', async () => {
    const { fetch, calls } = fakeFetch([{ status: 200, body: 'sent' }]);
    const api = load().wptoolkit.__clients[VERSION](config, fetch);

    await api.call('contact', { message: 'hi there' });

    assert.equal(calls[0].url, config.ajaxUrl);
    assert.equal(calls[0].init.body, 'action=my_plugin_contact&message=hi%20there&_wpnonce=ajax-nonce');
});

test('errors carry the server status, code, message and field details', async () => {
    const { fetch } = fakeFetch([{ status: 422, body: { code: 'validation_failed', message: 'Some fields are not valid.', details: { fields: { title: ['Title is required.'] } } } }]);
    const api = load().wptoolkit.__clients[VERSION](config, fetch);

    await assert.rejects(api.call('books', {}), (error) => {
        assert.equal(error.name, 'ToolkitError');
        assert.equal(error.status, 422);
        assert.equal(error.code, 'validation_failed');
        assert.deepEqual(error.details.fields.title, ['Title is required.']);
        return true;
    });
});

test('204 resolves with null and unknown routes reject', async () => {
    const { fetch } = fakeFetch([{ status: 204 }]);
    const api = load().wptoolkit.__clients[VERSION](config, fetch);

    assert.equal(await api.call('books', {}), null);
    await assert.rejects(api.call('nope'), (error) => error.code === 'unknown_route');
});
