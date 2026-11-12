import {afterEach, describe, expect, it, vi} from 'vitest';
import {nextTick, reactive, ref} from 'vue';
import {createPdkAdmin, useActionStore, useFormBuilder, useNotificationStore} from '@myparcel-dev/pdk-admin';
import {createSession} from './session';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  createPdkAdmin: vi.fn(),
  useActionStore: vi.fn(),
  useFormBuilder: vi.fn(),
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
  const get = vi.fn<(action: string) => unknown>(() => ({id: 'registered'}));
  const registerOrderActions = vi.fn();
  const remove = vi.fn();
  const store = reactive({
    notifications: [] as Record<string, unknown>[],
    remove,
    add: vi.fn((notification: Record<string, unknown>) => store.notifications.push(notification)),
  });

  vi.mocked(createPdkAdmin).mockReturnValue({render, dispose} as never);
  vi.mocked(useActionStore).mockReturnValue({dispatch, get, registerOrderActions} as never);
  vi.mocked(useNotificationStore).mockReturnValue(store as never);

  return {apps, render, dispose, dispatch, get, registerOrderActions, remove, store};
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

  it('starts the pdk admin with the request headers of the caller', async () => {
    setup();
    const getRequestHeaders = () => ({Authorization: 'Bearer x'});

    await createSession(createRoot(), {getRequestHeaders});

    expect(createPdkAdmin).toHaveBeenCalledWith({getRequestHeaders});
  });

  it('unmounts what did render and stops the pdk admin when one render fails', async () => {
    const {render, dispose, apps} = setup();
    const error = new Error('render failed');
    const root = createRoot();

    root.insertAdjacentHTML('beforeend', '<div data-pdk-component="PluginSettings" id="pdk-plugin-settings-2"></div>');
    render.mockImplementationOnce(() => {
      const app = {unmount: vi.fn()};

      apps.push(app);

      return Promise.resolve(app);
    });
    render.mockRejectedValueOnce(error);

    await expect(createSession(root, options)).rejects.toBe(error);
    expect(apps[0].unmount).toHaveBeenCalledOnce();
    expect(dispose).toHaveBeenCalledOnce();
  });

  it('skips an app that did not mount', async () => {
    const {render} = setup();

    render.mockResolvedValue(undefined);
    const session = await createSession(createRoot(), options);

    expect(() => session.unmount()).not.toThrow();
  });

  it('fails when the pdk admin does not start', async () => {
    setup();
    vi.mocked(createPdkAdmin).mockReturnValue(undefined as never);

    await expect(createSession(createRoot(), options)).rejects.toThrow('did not start');
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

  it('registers the order actions first when no order component did', async () => {
    const {dispatch, get, registerOrderActions} = setup();
    const session = await createSession(createRoot(), options);

    get.mockReturnValue(undefined);
    await session.dispatchBulkAction('action_export', ['a']);

    expect(registerOrderActions).toHaveBeenCalledOnce();
    expect(registerOrderActions.mock.invocationCallOrder[0]).toBeLessThan(dispatch.mock.invocationCallOrder[0]);
  });

  it('does not register the order actions twice', async () => {
    const {registerOrderActions} = setup();
    const session = await createSession(createRoot(), options);

    await session.dispatchBulkAction('action_export', ['a']);

    expect(registerOrderActions).not.toHaveBeenCalled();
  });

  // Like a vue-form-builder form: its methods write to its own refs.
  class FakeForm {
    public readonly isDirty;

    public readonly isValid = ref(false);

    public readonly validate = vi.fn(async function (this: FakeForm) {
      this.isValid.value = this.valid;

      return this.valid;
    });

    public constructor(
      isDirty: boolean,
      private readonly values: Record<string, unknown> = {},
      private readonly valid = true,
    ) {
      this.isDirty = ref(isDirty);
    }

    public getValues() {
      return this.values;
    }
  }

  const sessionWithForms = async (forms: Record<string, FakeForm>) => {
    setup();
    vi.mocked(useFormBuilder).mockReturnValue({forms: ref(forms)} as never);

    return createSession(createRoot(), options);
  };

  it('returns the values of the forms that have changes', async () => {
    const changed = new FakeForm(true, {fitInMailbox: 2});
    const unchanged = new FakeForm(false, {packageType: 'package'});
    const session = await sessionWithForms({changed, unchanged});

    await expect(session.getChangedFormValues()).resolves.toEqual({fitInMailbox: 2});
    expect(changed.isValid.value).toBe(true);
    expect(unchanged.validate).not.toHaveBeenCalled();
  });

  it('returns null without changes', async () => {
    const session = await sessionWithForms({unchanged: new FakeForm(false)});

    await expect(session.getChangedFormValues()).resolves.toBeNull();
  });

  it('knows when a form has changes', async () => {
    expect((await sessionWithForms({changed: new FakeForm(true), unchanged: new FakeForm(false)})).hasChanges()).toBe(true);
    expect((await sessionWithForms({unchanged: new FakeForm(false)})).hasChanges()).toBe(false);
  });

  it('rejects when a changed form is not valid', async () => {
    const session = await sessionWithForms({invalid: new FakeForm(true, {}, false)});

    await expect(session.getChangedFormValues()).rejects.toThrow('not valid');
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

  it('forwards the notifications that a caller adds from a response', async () => {
    const {store} = setup();
    const onNotification = vi.fn();
    const session = await createSession(createRoot(), {...options, onNotification});

    session.addNotifications([{id: 'n1', category: 'action', variant: 'error', title: 'Not saved', content: []}] as never);
    await nextTick();

    expect(store.add).toHaveBeenCalledOnce();
    expect(onNotification).toHaveBeenCalledWith({variant: 'error', title: 'Not saved', message: ''});
  });
});
