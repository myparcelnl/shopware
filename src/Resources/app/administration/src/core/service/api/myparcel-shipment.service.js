const ApiService = Shopware.Classes.ApiService;

class MyParcelShipmentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'myparcel') {
        super(httpClient, loginService, apiEndpoint);
    }

    createShipment(data = { consignment_id: null, shipping_option_id: null, order_id: null, order_version_id: null, label_url: null, insured_amount: null }) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/shipment/create`,
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

export default MyParcelShipmentService;