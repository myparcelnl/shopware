const ApiService = Shopware.Classes.ApiService;

class MyParcelConsignmentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'myparcel') {
        super(httpClient, loginService, apiEndpoint);
    }

    createConsignments(data = { orders: null, label_positions: null, number_of_labels: null, package_type: null }) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/consignment/create-consignments`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    createLabels(data = { reference_ids: null, label_positions: null, number_of_labels: null }) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/consignment/download-labels`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getForShippingOption(data = { shipping_option_id: null }) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/consignment/get-for-shipping-option`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default MyParcelConsignmentService;