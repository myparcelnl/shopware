import {afterEach, describe, expect, it, vi} from 'vitest';
import {nextTick, reactive} from 'vue';
import {createPdkAdmin, useActionStore, useNotificationStore} from '@myparcel-dev/pdk-admin';
import {createSession} from './session';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  createPdkAdmin: vi.fn(),
  useActionStore: vi.fn(),
  useNotificationStore: vi.fn(),
  AdminAction: {
    OrdersEdit: 'ordersEdit',
    OrdersExport: 'ordersExport',
    OrdersPrint: 'ordersPrint',
    OrdersExportPrint: 'ordersExportPrint',
  },
  NotificationCategory: {Action: 'action', Api: 'api', General: 'general', Modal: 'modal'},
}));
vi.mock('./config', () => ({createConfig: vi.fn((getRequestHeaders) => ({getRequestHeaders}))}));

const createRoot = (): HTMLElement => {
  const root = document.createElement('div');

  root.innerHTML = `
    <span id="myparcel-pdk-boot" data-pdk-context="{}"></span>
    <div data-pdk-component="Notifications" data-pdk-context="{}" id="pdk-notifications-1"></div>`;
  document.body.append(root);

  return root;
};

const createCell = (id: string): HTMLElement => {
  const cell = document.createElement('div');

  cell.id = id;
  cell.dataset.pdkComponent = 'OrderListItem';
  document.body.append(cell);

  return cell;
};

const setup = () => {
  const apps: {unmount: ReturnType<typeof vi.fn>}[] = [];
  const render = vi.fn().mockImplementation(() => {
    const app = {unmount: vi.fn()};

    apps.push(app);

    return Promise.resolve(app);
  });
  const dispose = vi.fn();
  const dispatch = vi.fn();
  const remove = vi.fn();
  const store = reactive({notifications: [] as Record<string, unknown>[], remove});

  vi.mocked(createPdkAdmin).mockReturnValue({render, dispose} as never);
  vi.mocked(useActionStore).mockReturnValue({dispatch} as never);
  vi.mocked(useNotificationStore).mockReturnValue(store as never);

  return {apps, render, dispose, dispatch, remove, store};
};

const options = {getRequestHeaders: () => ({})};

describe('createSession', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.mocked(createPdkAdmin).mockReset();
  });

  it('renders the root on create and extra elements on render', async () => {
    const {render} = setup();
    const session = await createSession(createRoot(), options);

    expect(render.mock.calls).toEqual([['Notifications', '#pdk-notifications-1']]);

    await session.render([createCell('cell-1'), createCell('cell-2')]);

    expect(render.mock.calls.slice(1)).toEqual([
      ['OrderListItem', '#cell-1'],
      ['OrderListItem', '#cell-2'],
    ]);
    expect(createPdkAdmin).toHaveBeenCalledOnce();
  });

  it('unmounts rendered elements without stopping the session', async () => {
    const {apps, dispose} = setup();
    const session = await createSession(createRoot(), options);
    const unmountCells = await session.render([createCell('cell-1')]);

    unmountCells();

    expect(apps[1].unmount).toHaveBeenCalledOnce();
    expect(apps[0].unmount).not.toHaveBeenCalled();
    expect(dispose).not.toHaveBeenCalled();

    session.unmount();

    expect(apps[0].unmount).toHaveBeenCalledOnce();
    expect(dispose).toHaveBeenCalledOnce();
  });

  it('dispatches a bulk action with the order ids', async () => {
    const {dispatch} = setup();
    const session = await createSession(createRoot(), options);

    await session.dispatchBulkAction('action_export', ['a', 'b']);

    expect(dispatch).toHaveBeenCalledWith('ordersExport', {orderIds: ['a', 'b']});
  });

  it('does not dispatch without order ids', async () => {
    const {dispatch} = setup();
    const session = await createSession(createRoot(), options);

    await session.dispatchBulkAction('action_print', []);

    expect(dispatch).not.toHaveBeenCalled();
  });

  it('rejects an unknown bulk action', async () => {
    setup();
    const session = await createSession(createRoot(), options);

    await expect(session.dispatchBulkAction('action_nope' as never, ['a'])).rejects.toThrow('action_nope');
  });

  it('forwards action and api notifications and removes them from the pdk', async () => {
    const {store, remove} = setup();
    const onNotification = vi.fn();
    const session = await createSession(createRoot(), {...options, onNotification});

    store.notifications.push(
      {id: 'n1', category: 'action', variant: 'success', title: 'Done', content: ['one', 'two']},
      {id: 'n2', category: 'api', variant: 'error', content: 'Failed'},
    );
    await nextTick();

    expect(onNotification.mock.calls).toEqual([
      [{variant: 'success', title: 'Done', message: 'one<br>two'}],
      [{variant: 'error', title: undefined, message: 'Failed'}],
    ]);
    expect(remove).toHaveBeenCalledWith('n1');
    expect(remove).toHaveBeenCalledWith('n2');

    session.unmount();
    store.notifications.push({id: 'n3', category: 'action', variant: 'info', content: 'late'});
    await nextTick();

    expect(onNotification).toHaveBeenCalledTimes(2);
  });

  it('keeps general and modal notifications in the pdk', async () => {
    const {store, remove} = setup();
    const onNotification = vi.fn();

    await createSession(createRoot(), {...options, onNotification});
    store.notifications.push(
      {id: 'n1', category: 'general', variant: 'info', content: 'a'},
      {id: 'n2', category: 'modal', variant: 'info', content: 'b'},
    );
    await nextTick();

    expect(onNotification).not.toHaveBeenCalled();
    expect(remove).not.toHaveBeenCalled();
  });

  it('does not forward without onNotification', async () => {
    const {store, remove} = setup();

    await createSession(createRoot(), options);
    store.notifications.push({id: 'n1', category: 'action', variant: 'info', content: 'a'});
    await nextTick();

    expect(useNotificationStore).not.toHaveBeenCalled();
    expect(remove).not.toHaveBeenCalled();
  });
});
