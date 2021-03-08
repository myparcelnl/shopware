const ApiService = Shopware.Classes.ApiService;

class MyParcelShippingOptionsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'myparcel') {
        super(httpClient, loginService, apiEndpoint);
    }

    all() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/shipping-options/all`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default MyParcelShippingOptionsService;