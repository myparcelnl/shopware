import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';

const override = vi.fn();
const controller = {refresh: vi.fn(), dispatch: vi.fn(), destroy: vi.fn(), bulkActions: []};
const createOrderListController = vi.fn(() => controller);

vi.mock('./sw-order-list.scss', () => ({}));
vi.mock('../../../../service/pdk-loader', () => ({notifyShopware: () => 'notify'}));
vi.mock('../../../../service/order-list-controller', () => ({
    createOrderListController: (...args) => createOrderListController(...args),
}));

const DELIVERY_STATUS = 'primaryOrderDelivery.stateMachineState.name';

describe('sw-order-list override', () => {
    let component;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {override}, Mixin: {getByName: () => ({})}};
        await import('./index');
        [[, component]] = override.mock.calls;
    });

    beforeEach(() => {
        controller.refresh.mockReset();
        controller.dispatch.mockReset();
        controller.destroy.mockReset();
        controller.bulkActions = [];
        createOrderListController.mockClear();
    });

    const createList = (overrides = {}) => ({
        ...component.data(),
        acl: {can: (privilege) => privilege === 'myparcel.access'},
        orders: [{id: 'a'}, {id: 'b'}],
        $tc: (key) => key,
        $nextTick: (callback) => callback(),
        createNotificationError: vi.fn(),
        refreshMyparcel: vi.fn(),
        watchMyparcelColumns: vi.fn(),
        ...overrides,
    });

    describe('getOrderColumns', () => {
        const getOrderColumns = (list, columns) => {
            list.$super = vi.fn(() => columns);

            return component.methods.getOrderColumns.call(list).map(({property}) => property);
        };

        it('adds the MyParcel column after the delivery status', () => {
            const columns = [{property: 'orderNumber'}, {property: DELIVERY_STATUS}, {property: 'amountTotal'}];

            expect(getOrderColumns(createList(), columns))
                .toEqual(['orderNumber', DELIVERY_STATUS, 'myparcelShipments', 'amountTotal']);
        });

        it('adds the MyParcel column at the end without a delivery status column', () => {
            expect(getOrderColumns(createList(), [{property: 'orderNumber'}]))
                .toEqual(['orderNumber', 'myparcelShipments']);
        });

        it('adds no column for a user without the MyParcel privilege', () => {
            const list = createList({acl: {can: () => false}});

            expect(getOrderColumns(list, [{property: DELIVERY_STATUS}])).toEqual([DELIVERY_STATUS]);
        });
    });

    describe('refreshMyparcel', () => {
        const refresh = (list) => component.methods.refreshMyparcel.call(list);

        it('loads the orders of the grid and takes the bulk actions of the load', async () => {
            const list = createList({myparcelController: controller});
            controller.bulkActions = [{action: 'action_print', label: 'Print'}];

            await refresh(list);

            expect(controller.refresh).toHaveBeenCalledWith(['a', 'b']);
            expect(list.myparcelBulkActions).toEqual([{action: 'action_print', label: 'Print'}]);
        });

        it('clears the bulk actions and shows a notification when the load fails', async () => {
            const list = createList({myparcelController: controller, myparcelBulkActions: [{action: 'action_print'}]});
            controller.refresh.mockRejectedValue(new Error('network'));
            vi.spyOn(console, 'error').mockImplementation(() => {});

            await refresh(list);

            expect(list.myparcelBulkActions).toEqual([]);
            expect(list.createNotificationError)
                .toHaveBeenCalledWith({message: 'myparcel.shopware_admin_order_list_load_error'});
        });

        it('loads nothing without a controller or without the MyParcel privilege', async () => {
            await refresh(createList());
            await refresh(createList({myparcelController: controller, acl: {can: () => false}}));

            expect(controller.refresh).not.toHaveBeenCalled();
        });
    });

    describe('watchMyparcelColumns', () => {
        it('loads again when a column is shown or hidden, and stops the watch before it', () => {
            const stopBefore = vi.fn();
            const stopNew = vi.fn();
            const grid = {currentColumns: [{property: 'myparcelShipments', visible: true}]};
            const list = createList({
                $refs: {orderGrid: grid},
                $watch: vi.fn(() => stopNew),
                stopMyparcelColumnsWatch: stopBefore,
            });

            component.methods.watchMyparcelColumns.call(list);

            const [[getter, callback]] = list.$watch.mock.calls;

            expect(stopBefore).toHaveBeenCalled();
            expect(list.stopMyparcelColumnsWatch).toBe(stopNew);
            expect(getter()).toBe('myparcelShipments:true');

            callback();
            expect(list.refreshMyparcel).toHaveBeenCalled();
        });

        it('watches nothing without a grid', () => {
            const list = createList({$refs: {}, $watch: vi.fn()});

            component.methods.watchMyparcelColumns.call(list);

            expect(list.$watch).not.toHaveBeenCalled();
            expect(list.stopMyparcelColumnsWatch).toBeNull();
        });
    });

    describe('onMyparcelBulkAction', () => {
        it('runs the bulk action for the selected orders', async () => {
            const list = createList({myparcelController: controller, $refs: {orderGrid: {selection: {a: {}, c: {}}}}});

            await component.methods.onMyparcelBulkAction.call(list, 'action_print');

            expect(controller.dispatch).toHaveBeenCalledWith('action_print', ['a', 'c']);
        });

        it('shows a notification when the bulk action fails', async () => {
            const list = createList({myparcelController: controller, $refs: {orderGrid: {selection: {}}}});
            controller.dispatch.mockRejectedValue(new Error('network'));
            vi.spyOn(console, 'error').mockImplementation(() => {});

            await component.methods.onMyparcelBulkAction.call(list, 'action_print');

            expect(list.createNotificationError)
                .toHaveBeenCalledWith({message: 'myparcel.shopware_admin_order_list_action_error'});
        });
    });

    it('loads again and watches the columns after each load of the orders', () => {
        const list = createList();

        component.watch.orders.call(list);

        expect(list.refreshMyparcel).toHaveBeenCalled();
        expect(list.watchMyparcelColumns).toHaveBeenCalled();
    });

    it('creates the controller on mount with the cells of the grid', () => {
        const root = document.createElement('div');
        const el = document.createElement('div');
        el.innerHTML = '<div data-myparcel-order-id="a"></div><div data-myparcel-order-id="b"></div>';
        const list = createList({$refs: {myparcelPdkRoot: root}, $el: el});

        component.mounted.call(list);

        const [[options]] = createOrderListController.mock.calls;

        expect(list.myparcelController).toBe(controller);
        expect(options.root).toBe(root);
        expect(options.getCells()).toHaveLength(2);
        expect(options.isActive()).toBe(true);
        expect(options.onNotification).toBe('notify');
    });

    it('stops the column watch and the controller when the page closes', () => {
        const stop = vi.fn();
        const list = createList({myparcelController: controller, stopMyparcelColumnsWatch: stop});

        component.beforeUnmount.call(list);

        expect(list.myparcelActive).toBe(false);
        expect(stop).toHaveBeenCalled();
        expect(controller.destroy).toHaveBeenCalled();
    });
});
