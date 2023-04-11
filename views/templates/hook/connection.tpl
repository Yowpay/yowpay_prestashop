<div class="panel" id="banking-connection-response-container">
    <div class="panel-heading">{l s="Open Banking Connection" mod='yowpayment'}</div>
    <div class="container" id="banking-connection" data-remaining-time="{$remainingTime}" data-account-status="{$accountStatus}" data-url="{$url}">
        {if $accountStatus == 'not_provided'}
            <label class="control-label">{l s="No Bank data was provided, please, go to your yowpay account and add it." mod='yowpayment'}</label>
        {else}
            <div class="account-information">
                <div class="account-columns">
                    <label class="control-label">{l s="Account owner" mod='yowpayment'}</label>
                    <label class="control-label">{l s="IBAN" mod='yowpayment'}</label>
                    <label class="control-label">{l s="BIC/SWIFT" mod='yowpayment'}</label>
                    <label class="control-label">{l s="Open Banking Status" mod='yowpayment'}</label>
                </div>
                <div class="account-rows">
                    <p class="account-detail-text">{$accountOwner}</p>
                    <p class="account-detail-text">{$iban}</p>
                    <p class="account-detail-text">{$swift}</p>
                    <p class="account-detail-text">
                        {if $accountStatus == 'active'}
                            <img src="../modules/yowpayment/img/ok_icon.png" width="25" height="25"> <span class="account-status-connected">{l s="CONNECTED" mod='yowpayment'}</span>
                        {else}
                            <img src="../modules/yowpayment/img/milker_X_icon.svg" width="25" height="25"> <span class="account-status-not-connected">{$accountStatus|upper|replace:'_':' '}</span>
                        {/if}

                    </p>
                    <p class="account-detail-sub-text">{l s="Open Banking Consent expire at "  mod='yowpayment'} {$expirationTime}</p>
                    <p class="account-detail-sub-text">{l s="Take care to renew before the expiration date. YowPay use the open banking access to validate payments"  mod='yowpayment'}</p>
                </div>
            </div>
        {/if}
    </div>
    <div class="panel-footer">
            <a href="https://yowpay.com/account/banking"
               class="btn btn-primary col-lg-offset-4"
               target="_blank"
            >
                {l s="Renew the Consent"  mod='yowpayment'}
            </a>
    </div>
</div>
