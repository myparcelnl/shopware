import {AdminComponent, type InputAdminConfiguration, LogLevel, useAppInfo} from '@myparcel-dev/pdk-admin';
import {
  DefaultButtonGroup,
  DefaultCheckboxGroup,
  DefaultCodeEditor,
  DefaultCol,
  DefaultCurrencyInput,
  DefaultImage,
  DefaultMultiDateInput,
  DefaultMultiSelectInput,
  DefaultNumberInput,
  DefaultRow,
  DefaultShippingMethodsInput,
  DefaultTableCol,
  DefaultTableRow,
  DefaultTimeInput,
} from '@myparcel-dev/pdk-admin-preset-default';
import ShopwareBadge from './components/ShopwareBadge.vue';
import ShopwareBox from './components/ShopwareBox.vue';
import ShopwareButton from './components/ShopwareButton.vue';
import ShopwareCheckboxInput from './components/ShopwareCheckboxInput.vue';
import ShopwareDropOffInput from './components/ShopwareDropOffInput.vue';
import ShopwareDropdownButton from './components/ShopwareDropdownButton.vue';
import ShopwareFormGroup from './components/ShopwareFormGroup.vue';
import ShopwareHeading from './components/ShopwareHeading.vue';
import ShopwareIcon from './components/ShopwareIcon.vue';
import ShopwareLink from './components/ShopwareLink.vue';
import ShopwareLoader from './components/ShopwareLoader.vue';
import ShopwareModal from './components/ShopwareModal.vue';
import ShopwareNotification from './components/ShopwareNotification.vue';
import ShopwareRadioGroup from './components/ShopwareRadioGroup.vue';
import ShopwareRadioInput from './components/ShopwareRadioInput.vue';
import ShopwareSelectInput from './components/ShopwareSelectInput.vue';
import ShopwareSettingsDivider from './components/ShopwareSettingsDivider.vue';
import ShopwareTabNavButton from './components/ShopwareTabNavButton.vue';
import ShopwareTable from './components/ShopwareTable.vue';
import ShopwareTextArea from './components/ShopwareTextArea.vue';
import ShopwareTextInput from './components/ShopwareTextInput.vue';
import ShopwareToggleInput from './components/ShopwareToggleInput.vue';
import ShopwareTriStateInput from './components/ShopwareTriStateInput.vue';

export type RequestHeadersProvider = () => Record<string, string> | Promise<Record<string, string>>;

const FADE = 'mypa-fade';

/**
 * Create the PDK admin configuration with the components of preset-default and
 * the request headers of the Shopware admin.
 *
 * The Shopware* components have the look of the Shopware admin. The components of preset-default
 * that are left only render other PDK components or plain layout, so they get the same look.
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
    [AdminComponent.DropOffInput]: ShopwareDropOffInput,
    [AdminComponent.DropdownButton]: ShopwareDropdownButton,
    [AdminComponent.FormGroup]: ShopwareFormGroup,
    [AdminComponent.Heading]: ShopwareHeading,
    [AdminComponent.Icon]: ShopwareIcon,
    [AdminComponent.Image]: DefaultImage,
    [AdminComponent.Link]: ShopwareLink,
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
    [AdminComponent.SettingsDivider]: ShopwareSettingsDivider,
    [AdminComponent.ShippingMethodsInput]: DefaultShippingMethodsInput,
    [AdminComponent.TabNavButton]: ShopwareTabNavButton,
    [AdminComponent.TableCol]: DefaultTableCol,
    [AdminComponent.TableRow]: DefaultTableRow,
    [AdminComponent.Table]: ShopwareTable,
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
