import template from './sw-myparcel-consignments.html.twig';
import MyParcelConsignmentService from "../../../../core/service/api/myparcel-consignment.service";

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const PACKAGE_TYPE_PACKAGE_ID = 1;
const PACKAGE_TYPE_MAILBOX_ID = 2;
const PACKAGE_TYPE_LETTER_ID = 3;
const PACKAGE_TYPE_DIGITAL_STAMP_ID = 4;

const PACKAGE_TYPE_PACKAGE_SNIPPET = 'sw-myparcel.general.packageTypes.package';
const PACKAGE_TYPE_MAILBOX_SNIPPET = 'sw-myparcel.general.packageTypes.mailbox';
const PACKAGE_TYPE_LETTER_SNIPPET = 'sw-myparcel.general.packageTypes.letter';
const PACKAGE_TYPE_DIGITAL_STAMP_SNIPPET = 'sw-myparcel.general.packageTypes.digitalStamp';

const DELIVERY_TYPE_MORNING_ID = 1;
const DELIVERY_TYPE_STANDARD_ID = 2;
const DELIVERY_TYPE_EVENING_ID = 3;
const DELIVERY_TYPE_PICKUP_ID = 4;

const DELIVERY_TYPE_MORNING_SNIPPET = 'sw-myparcel.general.deliveryTypes.morning';
const DELIVERY_TYPE_STANDARD_SNIPPET = 'sw-myparcel.general.deliveryTypes.standard';
const DELIVERY_TYPE_EVENING_SNIPPET = 'sw-myparcel.general.deliveryTypes.evening';
const DELIVERY_TYPE_PICKUP_SNIPPET = 'sw-myparcel.general.deliveryTypes.pickup';

const CARRIER_POSTNL_ID = 1;
const CARRIER_BPOST_ID = 2;
const CARRIER_DPD_ID = 3;

const CARRIER_POSTNL_SNIPPET = 'sw-myparcel.general.carriers.postNL';
const CARRIER_BPOST_SNIPPET = 'sw-myparcel.general.carriers.bpost';
const CARRIER_DPD_SNIPPET = 'sw-myparcel.general.carriers.dpd';

Component.register('sw-myparcel-consignments', {
    template: template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    inject: [
        'repositoryFactory',
        'MyParcelConsignmentService',
        'systemConfigApiService'
    ],

    data() {
        return {
            isLoading: false,
            consignments: [],
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            createSingleLabel: {
                item: null,
                printSmallLabel: false,
                printPosition: [1,2,3,4],
                numberOfLabels: 1,
                showModal: false,
            },
            createMultipleLabels: {
                items: null,
                printSmallLabel: false,
                printPosition: [1,2,3,4],
                numberOfLabels: 1,
                showModal: false,
            },
            createSingleLabelLoading: false,
            createMultipleLabelsLoading: false,
            selectionCount: 0,
            selectedConsignments: null,
            selectedConsignmentIds: [],
            orderIdFilter: [],
            packageTypes: {
                [PACKAGE_TYPE_PACKAGE_ID]: this.$tc(PACKAGE_TYPE_PACKAGE_SNIPPET),
                [PACKAGE_TYPE_MAILBOX_ID]: this.$tc(PACKAGE_TYPE_MAILBOX_SNIPPET),
                [PACKAGE_TYPE_LETTER_ID]: this.$tc(PACKAGE_TYPE_LETTER_SNIPPET)
                //[PACKAGE_TYPE_DIGITAL_STAMP_ID]: this.$tc(PACKAGE_TYPE_DIGITAL_STAMP_SNIPPET)
            },
            deliveryTypes: {
                [DELIVERY_TYPE_MORNING_ID]: this.$tc(DELIVERY_TYPE_MORNING_SNIPPET),
                [DELIVERY_TYPE_STANDARD_ID]: this.$tc(DELIVERY_TYPE_STANDARD_SNIPPET),
                [DELIVERY_TYPE_EVENING_ID]: this.$tc(DELIVERY_TYPE_EVENING_SNIPPET),
                [DELIVERY_TYPE_PICKUP_ID]: this.$tc(DELIVERY_TYPE_PICKUP_SNIPPET)
            },
            carriers: {
                [CARRIER_POSTNL_ID]: this.$tc(CARRIER_POSTNL_SNIPPET),
                [CARRIER_BPOST_ID]: this.$tc(CARRIER_BPOST_SNIPPET),
                [CARRIER_DPD_ID]: this.$tc(CARRIER_DPD_SNIPPET)
            },
            page: 1,
            total: 0,
            limit: 25
        };
    },

    created(){
        this.setDefaultLabelSize();
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    mounted() {
        if (!!this.$route.params.orderId) {
            this.orderIdFilter.push(this.$route.params.orderId);
        }

        const criteria = new Criteria(this.page, this.limit);

        criteria.addAggregation(Criteria.count('countTotal', 'id'));

        this.consignmentRepository.search(criteria, Shopware.Context.api).then((response) => {
            this.total = response.aggregations.countTotal.count;
        });

        this.getList();
    },

    computed: {
        consignmentColumns() {
            return this.getConsignmentColumns();
        },

        createMultipleLabelsAvailable() {
            return !!this.selectedConsignmentIds && this.selectionCount > 0 || false;
        },

        downloadMultipleLabelsAvailable() {
            return !!this.selectedConsignmentIds && this.selectionCount > 0 || false;
        },

        consignmentRepository() {
            return this.repositoryFactory.create('kiener_my_parcel_shipment');
        },

        consignmentCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.orderIdFilter.length) {
                this.orderIdFilter.forEach(orderId => {
                    criteria.addFilter(Criteria.equals('orderId', orderId));
                });
            }

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('order');
            criteria.addAssociation('order.deliveries');
            criteria.addAssociation('shippingOption');

            return criteria;
        },
    },

    methods: {
        getConsignmentColumns() {
            return [{
                property: 'createdAt',
                label: 'sw-myparcel.columns.consignmentDateColumn',
                allowResize: true,
                primary: true
            }, {
                property: 'order.orderNumber',
                label: 'sw-myparcel.columns.orderColumn',
                allowResize: true
            }, {
                property: 'barCode',
                label: 'sw-myparcel.columns.trackingCodesColumn',
                allowResize: true
            }, {
                property: 'order.orderDateTime',
                label: 'sw-myparcel.columns.orderDateColumn',
                allowResize: true
            }, {
                property: 'shippingOption.packageType',
                label: 'sw-myparcel.columns.packageTypeColumn',
                allowResize: true
            },{
                property: 'shippingOption.deliveryDate',
                label: 'sw-myparcel.columns.deliveryDateColumn',
                allowResize: true
            }, {
                property: 'shippingOption.carrierId',
                label: 'sw-myparcel.columns.carrierColumn',
                allowResize: true
            }, {
                property: 'shippingOption.deliveryType',
                label: 'sw-myparcel.columns.deliveryTypeColumn',
                allowResize: true
            }, {
                property: 'shippingOption.requiresAgeCheck',
                label: 'sw-myparcel.columns.requiresAgeCheckColumn',
                align: 'center',
                allowResize: true
            }, {
                property: 'shippingOption.requiresSignature',
                label: 'sw-myparcel.columns.requiresSignatureColumn',
                align: 'center',
                allowResize: true
            }, {
                property: 'shippingOption.onlyRecipient',
                label: 'sw-myparcel.columns.onlyRecipientColumn',
                align: 'center',
                allowResize: true
            }, {
                property: 'shippingOption.returnIfNotHome',
                label: 'sw-myparcel.columns.returnIfNotHomeColumn',
                align: 'center',
                allowResize: true
            }, {
                property: 'shippingOption.largeFormat',
                label: 'sw-myparcel.columns.largeFormatColumn',
                align: 'center',
                allowResize: true
            }];
        },

        setDefaultLabelSize(){
            this.systemConfigApiService
                .getValues('KienerMyParcel.config')
                .then(response => {
                    if(response['KienerMyParcel.config.myParcelDefaultLabelFormat'] == 'A6') {
                        this.createSingleLabel.printSmallLabel = true;
                        this.createMultipleLabels.printSmallLabel = true;
                    }
                });
        },

        closeModals() {
            this.closeCreateSingleLabelModal();
            this.closeCreateMultipleLabelsModal();
        },

        closeCreateSingleLabelModal() {
            this.createSingleLabel.showModal = false;
            this.createSingleLabelLoading = false;
        },

        closeCreateMultipleLabelsModal() {
            this.createMultipleLabels.showModal = false;
            this.createMultipleLabelsLoading = false;
        },

        openCreateSingleLabelModal() {
            this.createSingleLabel.showModal = true;
        },

        openCreateMultipleLabelsModal() {
            this.createMultipleLabels.showModal = true;
        },

        createLabels(data) {
            this.MyParcelConsignmentService.createLabels(data)
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                            message: this.$tc('sw-myparcel.messages.createLabelSuccess')
                        });

                        if (!!response.labelUrl) {
                            document.location = response.labelUrl;
                        }
                    } else {
                        this.createNotificationSuccess({
                            title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                            message: this.$tc('sw-myparcel.messages.error')
                        });
                    }

                    this.closeModals();
                })
                .catch(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                        message: this.$tc('sw-myparcel.messages.error')
                    });

                    this.closeModals();
                });
        },

        getList() {
            this.isLoading = true;

            return this.consignmentRepository.search(this.consignmentCriteria, Shopware.Context.api).then((response) => {
                this.consignments = response;
                this.isLoading = false;

                return response;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onSelectionChanged(selected, selectionCount) {
            this.selectionCount = selectionCount;
            this.selectedConsignments = selected;
            this.selectedConsignmentIds = [];

            if (!!this.selectedConsignments) {
                for (let id in this.selectedConsignments) {
                    this.selectedConsignmentIds.push(id);
                }
            }
        },

        onSortColumn(column) {
            let sortDirection = this.sortDirection;
            sortDirection = sortDirection === 'ASC' ? 'DESC' : 'ASC';

            if (this.sortBy !== column.dataIndex) {
                sortDirection = 'ASC';
            }

            this.sortBy = column.dataIndex;
            this.sortDirection = sortDirection;
            this.getList();
        },

        onClearFilters() {
            this.orderIdFilter = [];
            this.$router.push({ name: 'sw.myparcel.consignments' });
        },

        onOpenCreateSingleLabelModal(item) {
            //KienerMyParcel.config.myParcelDefaultLabelFormat
            this.createSingleLabel.item = item;
            this.openCreateSingleLabelModal();
        },

        onOpenCreateMultipleLabelsModal() {
            this.createMultipleLabels.items = this.selectedConsignments;
            this.openCreateMultipleLabelsModal();
        },

        onCloseCreateSingleLabelModal() {
            this.closeCreateSingleLabelModal();
        },

        onCreateSingleLabel() {
            if (!!this.createSingleLabel.item.consignmentReference) {
                this.createSingleLabelLoading = true;

                let data = {
                    reference_ids: [this.createSingleLabel.item.consignmentReference],
                    label_positions: this.createSingleLabel.printSmallLabel === false ? this.createSingleLabel.printPosition : []
                };

                this.createLabels(data);
            }
        },

        onCloseCreateMultipleLabelsModal() {
            this.closeCreateMultipleLabelsModal();
        },

        onCreateMultipleLabels() {
            if (!!this.createMultipleLabels.items) {
                this.createMultipleLabelsLoading = true;

                let data = {
                    reference_ids: [],
                    label_positions: this.createMultipleLabels.printSmallLabel === false ? this.createMultipleLabels.printPosition : []
                };

                for (let id in this.createMultipleLabels.items) {
                    if (!!this.createMultipleLabels.items[id].consignmentReference) {
                        data.reference_ids.push(this.createMultipleLabels.items[id].consignmentReference);
                    }
                }

                if (data.reference_ids.length) {
                    this.createLabels(data);
                }
            }
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            this.getList();
        },
    }
});
