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

const makeSession = () => ({render: vi.fn().mockResolvedValue(() => {}), dispatchBulkAction: vi.fn().mockResolvedValue(), unmount: vi.fn()});

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
        openSession.mockResolvedValue({session, data: makeData({aa: '<b>one</b>', bb: '<b>two</b>'})});

        await create().refresh(['aa', 'bb']);

        expect(cells[0].innerHTML).toBe('<b>one</b>');
        expect(cells[1].innerHTML).toBe('<b>two</b>');
        expect(session.render).toHaveBeenCalledWith(cells);
    });

    it('ignores a stale order list response', async () => {
        const staleSession = makeSession();
        const freshSession = makeSession();
        let resolveStale;
        openSession
            .mockImplementationOnce(() => new Promise((resolve) => { resolveStale = resolve; }))
            .mockResolvedValueOnce({session: freshSession, data: makeData({aa: 'fresh'})});
        const controller = create();

        const stale = controller.refresh(['aa']);
        await controller.refresh(['aa']);
        resolveStale({session: staleSession, data: makeData({aa: 'stale'})});
        await stale;

        expect(staleSession.unmount).toHaveBeenCalled();
        expect(staleSession.render).not.toHaveBeenCalled();
        expect(cells[0].innerHTML).toBe('fresh');
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
            .mockResolvedValueOnce({session: first, data: makeData({aa: 'x'})})
            .mockResolvedValueOnce({session: second, data: makeData({aa: 'x'})});
        const controller = create();

        await controller.refresh(['aa']);
        cells = [makeCell('aa')];
        await controller.refresh(['aa']);

        expect(first.unmount).toHaveBeenCalled();
        expect(cells[0].innerHTML).toBe('x');
        expect(second.render).toHaveBeenCalledWith(cells);
    });

    it('does nothing without orders', async () => {
        const controller = create();

        await controller.refresh([]);

        expect(openSession).not.toHaveBeenCalled();
    });

    it('does not request the view while no cell is shown', async () => {
        cells = [];

        await create().refresh(['aa']);

        expect(openSession).not.toHaveBeenCalled();
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
