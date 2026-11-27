import {AdminComponent, type InputAdminConfiguration, LogLevel, useAppInfo} from '@myparcel-dev/pdk-admin';
import {
  DefaultButtonGroup,
  DefaultCheckboxGroup,
  DefaultCodeEditor,
  DefaultCol,
  DefaultCurrencyInput,
  DefaultDropOffInput,
  DefaultHeading,
  DefaultImage,
  DefaultLink,
  DefaultMultiDateInput,
  DefaultMultiSelectInput,
  DefaultNumberInput,
  DefaultRow,
  DefaultSettingsDivider,
  DefaultShippingMethodsInput,
  DefaultTable,
  DefaultTableCol,
  DefaultTableRow,
  DefaultTimeInput,
} from '@myparcel-dev/pdk-admin-preset-default';
import ShopwareBadge from './components/ShopwareBadge.vue';
import ShopwareBox from './components/ShopwareBox.vue';
import ShopwareButton from './components/ShopwareButton.vue';
import ShopwareCheckboxInput from './components/ShopwareCheckboxInput.vue';
import ShopwareDropdownButton from './components/ShopwareDropdownButton.vue';
import ShopwareFormGroup from './components/ShopwareFormGroup.vue';
import ShopwareIcon from './components/ShopwareIcon.vue';
import ShopwareLoader from './components/ShopwareLoader.vue';
import ShopwareModal from './components/ShopwareModal.vue';
import ShopwareNotification from './components/ShopwareNotification.vue';
import ShopwareRadioGroup from './components/ShopwareRadioGroup.vue';
import ShopwareRadioInput from './components/ShopwareRadioInput.vue';
import ShopwareSelectInput from './components/ShopwareSelectInput.vue';
import ShopwareShipmentLabelWrapper from './components/ShopwareShipmentLabelWrapper.vue';
import ShopwareTabNavButton from './components/ShopwareTabNavButton.vue';
import ShopwareTextArea from './components/ShopwareTextArea.vue';
import ShopwareTextInput from './components/ShopwareTextInput.vue';
import ShopwareToggleInput from './components/ShopwareToggleInput.vue';
import ShopwareTriStateInput from './components/ShopwareTriStateInput.vue';
import {withClass} from './withClass';

export type RequestHeadersProvider = () => Record<string, string> | Promise<Record<string, string>>;

const FADE = 'mypa-fade';

/**
 * Create the PDK admin configuration with the Shopware* components, the components of preset-default
 * that are left, and the request headers of the Shopware admin.
 *
 * How a component gets the look of the Shopware admin:
 * - A Shopware* component only where the markup or the behaviour differs from preset-default. Its
 *   look is BEM classes (mypa-block__element) in the SCSS partials, on the Meteor tokens.
 * - withClass(Default*, 'mypa-…') where only the class differs, so changes in preset-default come along.
 * - The other components of preset-default only render other PDK components or plain layout, so
 *   they get the same look.
 * The prefixed Tailwind utilities are only for layout (the cssUtilities of the PDK and the joined
 * buttons of a dropdown), not for colours or surfaces.
 * The preset icon only prints the name, so ShopwareIcon draws them.
 * Its dropdown opens on hover and has no menu styling, so ShopwareDropdownButton opens on click as a floating menu.
 * Its modal has no position or backdrop, so ShopwareModal shows it as a native modal dialog.
 * The utilities are the WooCommerce set, prefixed and scoped to .myparcel-pdk by Tailwind.
 */
export const createConfig = (getRequestHeaders: RequestHeadersProvider): InputAdminConfiguration => ({
  logLevel: LogLevel.Info,
  getRequestHeaders,
  // The Shopware admin routes with the hash; a tab name there is a route it does not know.
  useLocationHash: false,

  components: {
    [AdminComponent.Badge]: ShopwareBadge,
    [AdminComponent.Box]: ShopwareBox,
    [AdminComponent.ButtonGroup]: DefaultButtonGroup,
    [AdminComponent.Button]: ShopwareButton,
    [AdminComponent.CheckboxGroup]: DefaultCheckboxGroup,
    [AdminComponent.CheckboxInput]: ShopwareCheckboxInput,
    [AdminComponent.CodeEditor]: DefaultCodeEditor,
    [AdminComponent.Col]: DefaultCol,
    [AdminComponent.CurrencyInput]: DefaultCurrencyInput,
    [AdminComponent.DropOffInput]: withClass(DefaultDropOffInput, 'mypa-drop-off'),
    [AdminComponent.DropdownButton]: ShopwareDropdownButton,
    [AdminComponent.FormGroup]: ShopwareFormGroup,
    [AdminComponent.Heading]: withClass(DefaultHeading, (attrs) => ['mypa-heading', `mypa-heading--${attrs.level ?? 1}`]),
    [AdminComponent.Icon]: ShopwareIcon,
    [AdminComponent.Image]: DefaultImage,
    [AdminComponent.Link]: withClass(DefaultLink, 'mypa-link'),
    [AdminComponent.Loader]: ShopwareLoader,
    [AdminComponent.Modal]: ShopwareModal,
    [AdminComponent.MultiDateInput]: DefaultMultiDateInput,
    [AdminComponent.MultiSelectInput]: DefaultMultiSelectInput,
    [AdminComponent.Notification]: ShopwareNotification,
    [AdminComponent.NumberInput]: DefaultNumberInput,
    [AdminComponent.RadioGroup]: ShopwareRadioGroup,
    [AdminComponent.RadioInput]: ShopwareRadioInput,
    [AdminComponent.Row]: DefaultRow,
    [AdminComponent.SelectInput]: ShopwareSelectInput,
    [AdminComponent.SettingsDivider]: withClass(DefaultSettingsDivider, 'mypa-settings-divider'),
    [AdminComponent.ShipmentLabelWrapper]: ShopwareShipmentLabelWrapper,
    [AdminComponent.ShippingMethodsInput]: DefaultShippingMethodsInput,
    [AdminComponent.TabNavButton]: ShopwareTabNavButton,
    [AdminComponent.TableCol]: DefaultTableCol,
    [AdminComponent.TableRow]: DefaultTableRow,
    [AdminComponent.Table]: withClass(DefaultTable, 'mypa-table'),
    [AdminComponent.TextArea]: ShopwareTextArea,
    [AdminComponent.TextInput]: ShopwareTextInput,
    [AdminComponent.TimeInput]: DefaultTimeInput,
    [AdminComponent.ToggleInput]: ShopwareToggleInput,
    [AdminComponent.TriStateInput]: ShopwareTriStateInput,
  },

  cssUtilities: {
    animationSpin: 'mypa-animate-spin',
    animationLoading: 'mypa-opacity-50 mypa-pointer-events-none mypa-select-none mypa-animate-pulse',
    cursorDefault: 'mypa-cursor-default',
    displayFlex: 'mypa-flex',
    flexGrow: 'mypa-flex-grow',
    marginLAuto: 'mypa-ml-auto',
    marginYAuto: 'mypa-my-auto',
    textCenter: 'mypa-text-center',
    textColorError: 'mypa-text-red-500',
    textColorSuccess: 'mypa-text-green-500',
    whitespaceNoWrap: 'mypa-whitespace-nowrap',
  },

  transitions: {
    notification: FADE,
    shipmentBox: FADE,
    tabNavigation: FADE,
  },

  generateFieldId(field) {
    return `${useAppInfo().name}-${field.name}`;
  },
});
