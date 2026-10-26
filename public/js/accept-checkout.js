(function () {
    var form = document.querySelector('[data-accept-form]');

    if (!form || !window.Accept) {
        return;
    }

    form.addEventListener('submit', function (event) {
        var descriptor = form.querySelector('[name="data_descriptor"]');
        var value = form.querySelector('[name="data_value"]');

        if (!descriptor || !value || descriptor.value) {
            return;
        }

        var number = document.getElementById('accept-card-number');
        var month = document.getElementById('accept-month');
        var year = document.getElementById('accept-year');
        var code = document.getElementById('accept-code');

        if (!number || !month || !year || !code) {
            return;
        }

        event.preventDefault();

        window.Accept.dispatchData({
            authData: {
                apiLoginID: form.getAttribute('data-api-login'),
                clientKey: form.getAttribute('data-client-key')
            },
            cardData: {
                cardNumber: number.value,
                month: month.value,
                year: year.value,
                cardCode: code.value
            }
        }, function (response) {
            var alert = document.getElementById('accept-error');

            if (!response || !response.messages || response.messages.resultCode === 'Error') {
                if (alert) {
                    var message = response && response.messages && response.messages.message && response.messages.message[0]
                        ? response.messages.message[0].text
                        : 'The card form could not be tokenized.';
                    alert.textContent = message;
                }

                return;
            }

            descriptor.value = response.opaqueData.dataDescriptor;
            value.value = response.opaqueData.dataValue;
            number.value = '';
            code.value = '';
            form.submit();
        });
    });
}());
