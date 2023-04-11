{literal}
    <link rel="stylesheet" href="../modules/yowpayment/css/style.css" type="text/css" media="all"/>
{/literal}

{extends file="page.tpl"}


{block name='page_content'}
    <div class="container mt-5">
        <div class="row">
            <div class="success-container">
                <div class="success-header">
                    <img src="../modules/yowpayment/img/plugin-logo-txt-only.png"
                         class="card-img-top"
                         alt="..."
                         height="80"
                    >
                    <p class="yowpay-text card-text">{l  s="Your order will be validated soon automatically!"  mod='yowpayment'}</p>
                </div>
                <div class="success-details">
                    <a class="btn-primary" href="{$continueShoppingUrl}">{l  s="Continue Shopping"  mod='yowpayment'}</a>
                    <a class="btn-primary" href="{$orderListUrl}">{l  s="Go to order list"  mod='yowpayment'}</a>
                </div>
            </div>
        </div>
    </div>
{/block}
