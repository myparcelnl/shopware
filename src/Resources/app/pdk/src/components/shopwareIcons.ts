// The Regular icons of @shopware-ag/meteor-icon-kit, the set of the Shopware admin. Keep its
// version in package.json the same as in the Shopware administration package.
import ban from '@shopware-ag/meteor-icon-kit/icons/regular/ban.svg?raw';
import checkmark from '@shopware-ag/meteor-icon-kit/icons/regular/checkmark.svg?raw';
import chevronDown from '@shopware-ag/meteor-icon-kit/icons/regular/chevron-down.svg?raw';
import chevronUp from '@shopware-ag/meteor-icon-kit/icons/regular/chevron-up.svg?raw';
import download from '@shopware-ag/meteor-icon-kit/icons/regular/download.svg?raw';
import externalLink from '@shopware-ag/meteor-icon-kit/icons/regular/external-link.svg?raw';
import fileExport from '@shopware-ag/meteor-icon-kit/icons/regular/file-export.svg?raw';
import pencilS from '@shopware-ag/meteor-icon-kit/icons/regular/pencil-s.svg?raw';
import plus from '@shopware-ag/meteor-icon-kit/icons/regular/plus.svg?raw';
import printer from '@shopware-ag/meteor-icon-kit/icons/regular/printer.svg?raw';
import save from '@shopware-ag/meteor-icon-kit/icons/regular/save.svg?raw';
import sync from '@shopware-ag/meteor-icon-kit/icons/regular/sync.svg?raw';
import times from '@shopware-ag/meteor-icon-kit/icons/regular/times.svg?raw';
import trash from '@shopware-ag/meteor-icon-kit/icons/regular/trash.svg?raw';
import truck from '@shopware-ag/meteor-icon-kit/icons/regular/truck.svg?raw';
import undo from '@shopware-ag/meteor-icon-kit/icons/regular/undo.svg?raw';
import {AdminIcon} from '@myparcel-dev/pdk-admin';

const SHOPWARE_ICONS: Partial<Record<AdminIcon, string>> = {
  [AdminIcon.Add]: plus,
  [AdminIcon.ArrowDown]: chevronDown,
  [AdminIcon.ArrowUp]: chevronUp,
  [AdminIcon.Close]: times,
  [AdminIcon.Delete]: trash,
  [AdminIcon.Download]: download,
  [AdminIcon.Edit]: pencilS,
  [AdminIcon.Export]: fileExport,
  [AdminIcon.External]: externalLink,
  [AdminIcon.No]: ban,
  [AdminIcon.Print]: printer,
  [AdminIcon.Refresh]: sync,
  [AdminIcon.Return]: undo,
  [AdminIcon.Save]: save,
  [AdminIcon.Shipment]: truck,
  [AdminIcon.Spinner]: sync,
  [AdminIcon.Yes]: checkmark,
};

/**
 * Get the SVG markup of a Shopware icon, as the icon kit ships it. The PDK also
 * asks for package and delivery types, which have no icon.
 *
 * @returns undefined for a name without an icon
 */
export const getIconSvg = (icon: string): string | undefined => {
  // Own keys only: a name like "constructor" must not find a member of Object.prototype.
  return Object.hasOwn(SHOPWARE_ICONS, icon) ? SHOPWARE_ICONS[icon as AdminIcon] : undefined;
};
