$(document).ready(function () {
    const paymentExplanationElement = $('#payment-explanation');
    const radioBtn = document.querySelectorAll('input[name="CHEQUE_APP_FULL_EXPLANATION"]');
    radioBtn.forEach((item) => {
        item.addEventListener('change', (event) => {
            if (item.value === '1') {
                paymentExplanationElement.show();
            } else {
                paymentExplanationElement.hide();
            }
        });
    });


    const bankingConnectionElement = $('#banking-connection');
    let url = bankingConnectionElement.data('url');


    $.ajax({
        url: url,
        type: 'POST',
        data: {
            btnConnect: 1
        },
        success: (data) => {
            $("#banking-connection-response-container").replaceWith(data);
        }
    });
})