import template from './sw-myparcel-orders.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const DELIVERY_TYPE_MORNING = 1;
const DELIVERY_TYPE_EVENING = 3;

const ACTION_TYPE_DOWNLOAD = 'download';
const ACTION_TYPE_CREATE = 'create';

const CARRIER_POSTNL_ID = 1;
const CARRIER_BPOST_ID = 2;
const CARRIER_DPD_ID = 3;

const CARRIER_POSTNL_SNIPPET = 'sw-myparcel.general.carriers.postNL';
const CARRIER_BPOST_SNIPPET = 'sw-myparcel.general.carriers.bpost';
const CARRIER_DPD_SNIPPET = 'sw-myparcel.general.carriers.dpd';

const DELIVERY_TYPE_MORNING_ID = 1;
const DELIVERY_TYPE_STANDARD_ID = 2;
const DELIVERY_TYPE_EVENING_ID = 3;
const DELIVERY_TYPE_PICKUP_ID = 4;

const DELIVERY_TYPE_MORNING_SNIPPET = 'sw-myparcel.general.deliveryTypes.morning';
const DELIVERY_TYPE_STANDARD_SNIPPET = 'sw-myparcel.general.deliveryTypes.standard';
const DELIVERY_TYPE_EVENING_SNIPPET = 'sw-myparcel.general.deliveryTypes.evening';
const DELIVERY_TYPE_PICKUP_SNIPPET = 'sw-myparcel.general.deliveryTypes.pickup';

Component.register('sw-myparcel-orders', {
    template: template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'MyParcelConsignmentService',
        'systemConfigApiService'
    ],

    data() {
        return {
            isLoading: false,
            filterLoading: false,
            availableAffiliateCodes: [],
            affiliateCodeFilter: [],
            availableCampaignCodes: [],
            campaignCodeFilter: [],
            shippingOptions: [],
            shippingOptions2: [],
            sortBy: 'order.orderNumber',
            sortDirection: 'DESC',
            createSingleConsignmentLoading: false,
            createMultipleConsignmentsLoading: false,
            selectionCount: 0,
            selectedShippingOptions: null,
            selectedShippingOptionIds: [],
            createSingleConsignment: {
                item: null,
                actionType: ACTION_TYPE_DOWNLOAD,
                printSmallLabel: false,
                printPosition: [1,2,3,4],
                numberOfLabels: 1,
                showModal: false,
            },
            createMultipleConsignments: {
                items: [],
                actionType: ACTION_TYPE_DOWNLOAD,
                packageType: 1,
                printSmallLabel: false,
                printPosition: [1,2,3,4],
                showModal: false,
            },
            carriers: {
                [CARRIER_POSTNL_ID]: this.$tc(CARRIER_POSTNL_SNIPPET),
                [CARRIER_BPOST_ID]: this.$tc(CARRIER_BPOST_SNIPPET),
                [CARRIER_DPD_ID]: this.$tc(CARRIER_DPD_SNIPPET)
            },
            deliveryTypes: {
                [DELIVERY_TYPE_MORNING_ID]: this.$tc(DELIVERY_TYPE_MORNING_SNIPPET),
                [DELIVERY_TYPE_STANDARD_ID]: this.$tc(DELIVERY_TYPE_STANDARD_SNIPPET),
                [DELIVERY_TYPE_EVENING_ID]: this.$tc(DELIVERY_TYPE_EVENING_SNIPPET),
                [DELIVERY_TYPE_PICKUP_ID]: this.$tc(DELIVERY_TYPE_PICKUP_SNIPPET)
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

    mounted(){
        const criteria = new Criteria(this.page, this.limit);

        criteria.addAggregation(Criteria.count('countTotal', 'id'));

        this.shippingOptionRepository.search(criteria, Shopware.Context.api).then((response) => {
            this.total = response.aggregations.countTotal.count;
        });
    },

    computed: {
        orderColumns() {
            return this.getOrderColumns();
        },

        createMultipleConsignmentsAvailable() {
            return !!this.selectedShippingOptionIds && this.selectionCount > 0 || false;
        },

        shippingOptionRepository() {
            return this.repositoryFactory.create('kiener_my_parcel_shipping_option');
        },

        shippingOptionCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            if (this.affiliateCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('affiliateCode', this.affiliateCodeFilter));
            }
            if (this.campaignCodeFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('campaignCode', this.campaignCodeFilter));
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('order');
            criteria.addAssociation('order.addresses');
            criteria.addAssociation('order.salesChannel');
            criteria.addAssociation('order.orderCustomer');
            criteria.addAssociation('order.currency');
            criteria.addAssociation('order.transactions');
            criteria.addAssociation('order.deliveries');

            return criteria;
        },
    },

    methods: {
        getOrderColumns() {
            return [{
                property: 'order.orderNumber',
                label: 'sw-order.list.columnOrderNumber',
                allowResize: true,
                primary: true
            }, {
                property: 'order.salesChannel.name',
                label: 'sw-order.list.columnSalesChannel',
                allowResize: true
            }, {
                property: 'order.orderCustomer.firstName',
                dataIndex: 'order.orderCustomer.firstName,order.orderCustomer.lastName',
                label: 'sw-order.list.columnCustomerName',
                allowResize: true
            }, {
                property: 'order.billingAddressId',
                label: 'sw-order.list.columnBillingAddress',
                allowResize: true
            }, {
                property: 'order.amountTotal',
                label: 'sw-order.list.columnAmount',
                align: 'right',
                allowResize: true
            }, {
                property: 'order.stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true
            }, {
                property: 'order.transactions[0].stateMachineState.name',
                label: 'sw-order.list.columnTransactionState',
                allowResize: true
            }, {
                property: 'order.deliveries[0].stateMachineState.name',
                label: 'sw-order.list.columnDeliveryState',
                allowResize: true
            }, {
                property: 'deliveryDate',
                label: 'sw-myparcel.columns.deliveryDateColumn',
                allowResize: true
            },{
                property: 'carrierId',
                label: 'sw-myparcel.columns.carrierColumn',
                allowResize: true
            }, {
                property: 'deliveryType',
                label: 'sw-myparcel.columns.deliveryTypeColumn',
                allowResize: true
            }, {
                property: 'numberOfConsignments',
                label: 'sw-myparcel.columns.numberOfConsignmentsColumn',
                allowResize: true
            }, {
                property: 'order.orderDateTime',
                label: 'sw-order.list.orderDate',
                allowResize: true
            }, {
                property: 'order.affiliateCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnAffiliateCode',
                allowResize: true,
                visible: false
            }, {
                property: 'order.campaignCode',
                inlineEdit: 'string',
                label: 'sw-order.list.columnCampaignCode',
                allowResize: true,
                visible: false
            }];
        },

        setDefaultLabelSize(){
            this.systemConfigApiService
                .getValues('KienerMyParcel.config')
                .then(response => {
                    if(response['KienerMyParcel.config.myParcelDefaultLabelFormat'] == 'A6') {
                        this.createSingleConsignment.printSmallLabel = true;
                        this.createMultipleConsignments.printSmallLabel = true;
                    }
                });
        },

        getNumberOfConsignments(shippingOptionId) {
            const gridItem = this.$refs[shippingOptionId];

            if (!!gridItem) {
                gridItem.innerHTML = '0';

                this.MyParcelConsignmentService.getForShippingOption({
                    shipping_option_id: shippingOptionId
                })
                    .then((response) => {

                        if (response.success === true) {
                            let length = 0;

                            if (!!response.consignments) {
                                for (let id in response.consignments) {
                                    length = length + 1;
                                }
                            }
                            gridItem.innerHTML = length.toString();
                        }
                    });
            }
        },

        getBillingAddress(order) {
            return order.addresses.find((address) => {
                return address.id === order.billingAddressId;
            });
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state', order.stateMachineState.technicalName
            ).variant;
        },

        getVariantFromPaymentState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', order.transactions[0].stateMachineState.technicalName
            ).variant;
        },

        getVariantFromDeliveryState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order_delivery.state', order.deliveries[0].stateMachineState.technicalName
            ).variant;
        },

        closeModals() {
            this.closeCreateSingleConsignmentModal();
            this.closeCreateMultipleConsignmentsModal();
        },

        closeCreateSingleConsignmentModal() {
            this.createSingleConsignment.showModal = false;
            this.createSingleConsignmentLoading = false;
        },

        closeCreateMultipleConsignmentsModal() {
            this.createMultipleConsignments.showModal = false;
            this.createMultipleConsignmentsLoading = false;
        },

        openCreateSingleConsignmentModal() {
            this.createSingleConsignment.showModal = true;
        },

        openCreateMultipleConsignmentsModal() {
            this.createMultipleConsignments.showModal = true;
        },

        saveSingleConsignment(consignmentData) {
            this.createSingleConsignmentLoading = true;

            let order = {
                order_id: consignmentData.item.order.id,
                order_version_id: consignmentData.item.order.versionId,
                shipping_option_id: consignmentData.item.id,
                package_type: consignmentData.item.packageType,
            };

            consignmentData.packageType = consignmentData.item.packageType;

            this.createConsignments([order], consignmentData);
        },

        saveMultipleConsignments(consignmentData) {
            this.createMultipleConsignmentsLoading = true;

            let orders = [];

            for (let id in consignmentData.items) {
                let item = null;

                if (!!consignmentData.items[id]) {
                    item = consignmentData.items[id];
                }

                if (!!item) {
                    orders.push({
                        order_id: item.orderId,
                        order_version_id: item.orderVersionId,
                        shipping_option_id: id,
                        package_type: consignmentData.packageType,
                    });
                }
            }

            if (orders.length) {
                this.createConsignments(orders, consignmentData);
            }
        },

        createConsignments(orders, consignmentData) {
            this.MyParcelConsignmentService.createConsignments({
                orders: orders,
                label_positions: consignmentData.printSmallLabel === false ? consignmentData.printPosition : [],
                number_of_labels: consignmentData.numberOfLabels === false ? 1 : consignmentData.numberOfLabels,
                package_type: consignmentData.packageType,
            })
                .then((response) => {
                    if (response.success === true) {
                        this.createNotificationSuccess({
                            title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                            message: this.$tc('sw-myparcel.messages.consignmentSuccess')
                        });

                        if (
                            !!response.labelUrl
                            && consignmentData.actionType === ACTION_TYPE_DOWNLOAD
                        ) {
                            document.location = response.labelUrl;
                        }
                    } else {
                        this.createNotificationError({
                            title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                            message: this.$tc('sw-myparcel.messages.error')
                        });
                    }

                    this.closeModals();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$tc('sw-myparcel.general.mainMenuItemGeneral'),
                        message: this.$tc('sw-myparcel.messages.error')
                    });

                    this.closeModals();
                });
        },

        getList() {
            this.isLoading = true;
            return this.shippingOptionRepository.search(this.shippingOptionCriteria, Shopware.Context.api).then((response) => {
                this.shippingOptions = [];
                response.forEach(item => {
                    if (
                        !!item.order.deliveries
                        && item.order.deliveries.length !== 0
                        && !!item.order.deliveries[0].stateMachineState.name
                        && item.order.deliveries[0].stateMachineState.name.toLowerCase() === 'open'
                    ) {
                        this.shippingOptions.push(item);
                    }

                    if (
                        !item.order.deliveries
                        || item.order.deliveries.length === 0
                        || !item.order.deliveries[0].stateMachineState.name
                    ) {
                        this.shippingOptions.push(item);
                    }
                });

                this.isLoading = false;

                if (!!this.shippingOptions) {
                    this.shippingOptions.forEach(item => this.getNumberOfConsignments(item.id));
                }

                return response;
            }).catch((error) => {
                this.isLoading = false;
            });
        },

        onlyRecipientChecked(item) {
            return this.onlyRecipientDisabled(item) === true
                || item.onlyRecipient;
        },

        onlyRecipientDisabled(item) {
            return item.deliveryType === DELIVERY_TYPE_MORNING
                || item.deliveryType === DELIVERY_TYPE_EVENING;
        },

        onSelectionChanged(selected, selectionCount) {
            this.selectionCount = selectionCount;
            this.selectedShippingOptions = selected;
            this.selectedShippingOptionIds = [];

            if (!!this.selectedShippingOptions) {
                for (let id in this.selectedShippingOptions) {
                    this.selectedShippingOptionIds.push(id);
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

        onOpenCreateSingleConsignmentModal(item) {
            this.createSingleConsignment.item = item;
            this.selectedShippingOptionIds = [item.id];
            this.createSingleConsignment.numberOfLabels = 1;
            this.createSingleConsignment.showModal = true;
        },

        onCloseCreateSingleConsignmentModal() {
            this.closeCreateSingleConsignmentModal()
        },

        onCreateSingleConsignment() {
            if (!!this.createSingleConsignment.item) {
                this.shippingOptionRepository.save(this.createSingleConsignment.item, Shopware.Context.api)
                    .then(() => {
                        this.saveSingleConsignment(this.createSingleConsignment);
                    })
                    .catch(() => {
                        //
                    });
            }
        },

        onOpenCreateMultipleConsignmentsModal() {
            if (
                !!this.selectedShippingOptionIds
                && this.selectedShippingOptionIds.length
            ) {
                this.createMultipleConsignments.items = this.selectedShippingOptions;
                this.openCreateMultipleConsignmentsModal();
            }
        },

        onCloseCreateMultipleConsignmentsModal() {
            this.closeCreateMultipleConsignmentsModal();
        },

        onCreateMultipleConsignments() {
            this.saveMultipleConsignments(this.createMultipleConsignments);
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            this.getList();
        },
    }
});
