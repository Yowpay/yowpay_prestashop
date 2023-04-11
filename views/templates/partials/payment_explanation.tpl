{literal}
    <link rel="stylesheet" href="../modules/yowpayment/css/style.css" type="text/css" media="all"/>
{/literal}

<div class="container mt-5" id="payment-explanation">
    <div class="row">
        <div class="col-sm-4">
            <div class="card">
                <img src="../modules/yowpayment/img/plugin-black-explain-bank.png" class="card-img-top"
                     alt="..." width="80" height="80">
                <div class="card-body">
                    <p class="yowpay-text card-text">{l s="Connect to your banking app"  mod='yowpayment'}</p>
                </div>
            </div>
            <div class="arrow-wrapper">
                <div class="arrow"></div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="card">
                <img src="../modules/yowpayment/img/plugin-black-explain-qrcode.png" class="card-img-top" alt="..."
                     width="80" height="80">
                <div class="card-body">
                    <p class="yowpay-text card-text">{l s="Scan the QR Code or enter the payment details manually"  mod='yowpayment'}</p>
                </div>
            </div>

            <div class="arrow-wrapper">
                <div class="arrow"></div>
            </div>
        </div>
        <div class="col-sm-4">
            <i class="fas fa-arrow-right fa-3x text-muted d-none d-md-block"></i>
            <div class="card">
                <img src="../modules/yowpayment/img/plugin-black-explain-ok.png" class="card-img-top" alt="..."
                     width="80" height="80">
                <div class="card-body">
                    <p class="yowpay-text card-text">{l s="Validate your transfer"  mod='yowpayment'}</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../modules/yowpayment/views/js/yowpay.js"></script>