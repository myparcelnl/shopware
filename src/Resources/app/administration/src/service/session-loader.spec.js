import {beforeEach, describe, expect, it, vi} from 'vitest';

const openSession = vi.fn();

vi.mock('./pdk-loader', () => ({openSession: (...args) => openSession(...args)}));

const {createSessionLoader} = await import('./session-loader');

const makeSession = () => ({unmount: vi.fn()});

describe('session-loader', () => {
    let root;
    let isActive;
    let onNotification;

    const create = () => createSessionLoader({root, isActive: () => isActive, onNotification});

    beforeEach(() => {
        document.body.innerHTML = '';
        openSession.mockReset();
        root = document.createElement('div');
        document.body.appendChild(root);
        isActive = true;
        onNotification = vi.fn();
    });

    it('opens the view in a new child element of root', async () => {
        const session = makeSession();
        openSession.mockResolvedValue({session, data: {html: ''}});

        const result = await create().load('order', {orderId: 'aa'});

        const [view, params, element, isCurrent, options] = openSession.mock.calls[0];

        expect([view, params]).toEqual(['order', {orderId: 'aa'}]);
        expect(element.parentElement).toBe(root);
        expect(isCurrent()).toBe(true);
        expect(options).toEqual({onNotification});
        expect(result.session).toBe(session);
        expect(result.data).toEqual({html: ''});
        expect(result.isCurrent()).toBe(true);
    });

    it('closes the session before it and removes its element', async () => {
        const first = makeSession();
        openSession
            .mockResolvedValueOnce({session: first, data: {}})
            .mockResolvedValueOnce({session: makeSession(), data: {}});
        const loader = create();

        await loader.load('order', {orderId: 'aa'});
        const firstElement = openSession.mock.calls[0][2];
        await loader.load('order', {orderId: 'bb'});

        expect(first.unmount).toHaveBeenCalled();
        expect(firstElement.isConnected).toBe(false);
        expect(root.children).toHaveLength(1);
    });

    it('throws away a load that a newer load replaced', async () => {
        const stale = makeSession();
        const fresh = makeSession();
        let resolveStale;
        openSession
            .mockImplementationOnce(() => new Promise((resolve) => { resolveStale = resolve; }))
            .mockResolvedValueOnce({session: fresh, data: {}});
        const loader = create();

        const staleLoad = loader.load('order', {orderId: 'aa'});
        const staleElement = openSession.mock.calls[0][2];
        await loader.load('order', {orderId: 'bb'});
        resolveStale({session: stale, data: {}});

        await expect(staleLoad).resolves.toBeNull();
        expect(stale.unmount).toHaveBeenCalled();
        expect(staleElement.isConnected).toBe(false);
        expect(loader.session).toBe(fresh);
    });

    it('ignores the failure of a load that a newer load replaced', async () => {
        let rejectStale;
        openSession
            .mockImplementationOnce(() => new Promise((resolve, reject) => { rejectStale = reject; }))
            .mockResolvedValueOnce({session: makeSession(), data: {}});
        const loader = create();

        const staleLoad = loader.load('order', {orderId: 'aa'});
        const staleElement = openSession.mock.calls[0][2];
        await loader.load('order', {orderId: 'bb'});
        rejectStale(new Error('network'));

        await expect(staleLoad).resolves.toBeNull();
        expect(staleElement.isConnected).toBe(false);
    });

    it('rejects when the current load fails', async () => {
        openSession.mockRejectedValue(new Error('network'));

        await expect(create().load('order', {orderId: 'aa'})).rejects.toThrow('network');
    });

    it('throws away a load that finishes after the page closed', async () => {
        const session = makeSession();
        let resolveLoad;
        openSession.mockImplementationOnce(() => new Promise((resolve) => { resolveLoad = resolve; }));

        const load = create().load('order', {orderId: 'aa'});
        isActive = false;
        resolveLoad({session, data: {}});

        await expect(load).resolves.toBeNull();
        expect(session.unmount).toHaveBeenCalled();
    });

    it('throws away a load that finishes after close', async () => {
        const session = makeSession();
        let resolveLoad;
        openSession.mockImplementationOnce(() => new Promise((resolve) => { resolveLoad = resolve; }));
        const loader = create();

        const load = loader.load('order', {orderId: 'aa'});
        loader.close();
        resolveLoad({session, data: {}});

        await expect(load).resolves.toBeNull();
        expect(session.unmount).toHaveBeenCalled();
        expect(root.children).toHaveLength(0);
    });

    it('closes the open session and removes its element', async () => {
        const session = makeSession();
        openSession.mockResolvedValue({session, data: {}});
        const loader = create();

        await loader.load('order', {orderId: 'aa'});
        loader.close();

        expect(session.unmount).toHaveBeenCalled();
        expect(loader.session).toBeNull();
        expect(root.children).toHaveLength(0);
    });
});
