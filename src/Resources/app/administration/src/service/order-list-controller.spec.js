import {beforeEach, describe, expect, it, vi} from 'vitest';

const openSession = vi.fn();

vi.mock('./pdk-loader', () => ({openSession: (...args) => openSession(...args)}));

const {createOrderListController} = await import('./order-list-controller');

const makeCell = (id) => {
    const cell = document.createElement('div');

    cell.dataset.myparcelOrderId = id;
    document.body.appendChild(cell);

    return cell;
};

const makeSession = () => ({
    render: vi.fn().mockResolvedValue(() => {}),
    dispatchBulkAction: vi.fn().mockResolvedValue(),
    isBulkAction: (action) => action.startsWith('action_export'),
    unmount: vi.fn(),
});

const item = (n) => `<div id="pdk-order-list-item-${n}" data-pdk-component="OrderListItem"></div>`;

const makeData = (items = {}, bulkActions = []) => ({html: '', items, bulkActions});

describe('order-list-controller', () => {
    let root;
    let cells;
    let isActive;

    const create = () => createOrderListController({
        root,
        getCells: () => cells,
        isActive: () => isActive,
        onNotification: vi.fn(),
    });

    beforeEach(() => {
        document.body.innerHTML = '';
        openSession.mockReset();
        root = document.createElement('div');
        document.body.appendChild(root);
        cells = [makeCell('aa'), makeCell('bb')];
        isActive = true;
    });

    it('requests the view for all visible orders in one call', async () => {
        openSession.mockResolvedValue({session: makeSession(), data: makeData()});

        await create().refresh(['aa', 'bb']);

        expect(openSession).toHaveBeenCalledTimes(1);
        expect(openSession).toHaveBeenCalledWith('orderList', {orderIds: ['aa', 'bb']}, expect.any(HTMLElement), expect.any(Function), expect.any(Object));
    });

    it('fills each cell with its own item', async () => {
        const session = makeSession();
        openSession.mockResolvedValue({session, data: makeData({aa: item(1), bb: item(2)})});

        await create().refresh(['aa', 'bb']);

        expect(cells[0].innerHTML).toBe(item(1));
        expect(cells[1].innerHTML).toBe(item(2));
        expect(session.render).toHaveBeenCalledWith([
            cells[0].querySelector('[data-pdk-component]'),
            cells[1].querySelector('[data-pdk-component]'),
        ]);
    });

    it('ignores a stale order list response', async () => {
        const staleSession = makeSession();
        const freshSession = makeSession();
        let resolveStale;
        openSession
            .mockImplementationOnce(() => new Promise((resolve) => { resolveStale = resolve; }))
            .mockResolvedValueOnce({session: freshSession, data: makeData({aa: item('fresh')})});
        const controller = create();

        const stale = controller.refresh(['aa']);
        await controller.refresh(['aa']);
        resolveStale({session: staleSession, data: makeData({aa: item('stale')})});
        await stale;

        expect(staleSession.unmount).toHaveBeenCalled();
        expect(staleSession.render).not.toHaveBeenCalled();
        expect(cells[0].innerHTML).toBe(item('fresh'));
    });

    it('gives each load its own root element', async () => {
        openSession.mockResolvedValue({session: makeSession(), data: makeData()});
        const controller = create();

        await controller.refresh(['aa']);
        await controller.refresh(['aa']);

        const [first, second] = openSession.mock.calls.map((call) => call[2]);

        expect(first).not.toBe(second);
        expect(first.isConnected).toBe(false);
        expect(second.parentElement).toBe(root);
    });

    it('renders again when the grid re-renders the cells', async () => {
        const first = makeSession();
        const second = makeSession();
        openSession
            .mockResolvedValueOnce({session: first, data: makeData({aa: item('x')})})
            .mockResolvedValueOnce({session: second, data: makeData({aa: item('x')})});
        const controller = create();

        await controller.refresh(['aa']);
        cells = [makeCell('aa')];
        await controller.refresh(['aa']);

        expect(first.unmount).toHaveBeenCalled();
        expect(cells[0].innerHTML).toBe(item('x'));
        expect(second.render).toHaveBeenCalledWith([cells[0].querySelector('[data-pdk-component]')]);
    });

    it('does nothing without orders', async () => {
        const controller = create();

        await controller.refresh([]);

        expect(openSession).not.toHaveBeenCalled();
    });

    it('leaves out a bulk action that the session does not know', async () => {
        const session = makeSession();
        const known = {action: 'action_export', label: 'Export'};
        openSession.mockResolvedValue({session, data: makeData({}, [known, {action: 'action_new', label: 'New'}])});
        cells = [];
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const controller = create();

        await controller.refresh(['aa']);

        expect(controller.bulkActions).toEqual([known]);
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('action_new'));
    });

    it('loads the bulk actions while the column is hidden', async () => {
        const session = makeSession();
        const bulkActions = [{action: 'action_export', label: 'Export'}];
        openSession.mockResolvedValue({session, data: makeData({}, bulkActions)});
        cells = [];
        const controller = create();

        await controller.refresh(['aa']);
        await controller.dispatch('action_export', ['aa']);

        expect(openSession).toHaveBeenCalledTimes(1);
        expect(session.render).not.toHaveBeenCalled();
        expect(controller.bulkActions).toEqual(bulkActions);
        expect(session.dispatchBulkAction).toHaveBeenCalledWith('action_export', ['aa']);
    });

    it('stops a render that finishes after a newer refresh started', async () => {
        const stop = vi.fn();
        const slow = makeSession();
        let resolveRender;
        slow.render.mockImplementation(() => new Promise((resolve) => { resolveRender = resolve; }));
        openSession
            .mockResolvedValueOnce({session: slow, data: makeData({aa: item(1)})})
            .mockResolvedValueOnce({session: makeSession(), data: makeData({aa: item(2)})});
        const controller = create();

        const first = controller.refresh(['aa']);
        await vi.waitFor(() => expect(slow.render).toHaveBeenCalled());
        await controller.refresh(['aa']);
        resolveRender(stop);
        await first;

        expect(stop).toHaveBeenCalled();
    });

    it('ignores a failed load that a newer refresh replaced, and removes its element', async () => {
        const fresh = makeSession();
        const bulkActions = [{action: 'action_export', label: 'Export'}];
        let rejectStale;
        openSession
            .mockImplementationOnce(() => new Promise((resolve, reject) => { rejectStale = reject; }))
            .mockResolvedValueOnce({session: fresh, data: makeData({aa: item('fresh')}, bulkActions)});
        const controller = create();

        const stale = controller.refresh(['aa']);
        await controller.refresh(['aa']);
        const [staleElement] = openSession.mock.calls.map((call) => call[2]);
        rejectStale(new Error('network'));

        await expect(stale).resolves.toBeUndefined();
        expect(staleElement.isConnected).toBe(false);
        expect(controller.bulkActions).toEqual(bulkActions);
        expect(cells[0].innerHTML).toBe(item('fresh'));
    });

    it('ignores a failed render that a newer refresh replaced', async () => {
        const slow = makeSession();
        let rejectRender;
        slow.render.mockImplementation(() => new Promise((resolve, reject) => { rejectRender = reject; }));
        openSession
            .mockResolvedValueOnce({session: slow, data: makeData({aa: item(1)})})
            .mockResolvedValueOnce({session: makeSession(), data: makeData({aa: item(2)})});
        const controller = create();

        const first = controller.refresh(['aa']);
        await vi.waitFor(() => expect(slow.render).toHaveBeenCalled());
        await controller.refresh(['aa']);
        rejectRender(new Error('render'));

        await expect(first).resolves.toBeUndefined();
    });

    it('still rejects when the current load fails', async () => {
        openSession.mockRejectedValue(new Error('network'));

        await expect(create().refresh(['aa'])).rejects.toThrow('network');
    });

    it('dispatches the bulk action through the session', async () => {
        const session = makeSession();
        const bulkActions = [{action: 'action_export', label: 'Export'}];
        openSession.mockResolvedValue({session, data: makeData({}, bulkActions)});
        const controller = create();

        await controller.refresh(['aa']);
        await controller.dispatch('action_export', ['aa', 'bb']);

        expect(controller.bulkActions).toEqual(bulkActions);
        expect(session.dispatchBulkAction).toHaveBeenCalledWith('action_export', ['aa', 'bb']);
    });
});
