{**
 * MIT License
 * Copyright (c) 2023 Yowpay - Peer to Peer SEPA Payments made easy

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author   YowPay SARL
 * @copyright  YowPay SARL
 * @license  MIT License
 *}
<div class="panel" id="banking-connection-response-container">
    <div class="panel-heading">{l s="Open Banking Connection" mod='yowpayment'}</div>
    <div class="container" id="banking-connection" data-remaining-time="{$remainingTime}" data-account-status="{$accountStatus}" data-url="{$url}">
        {if $accountStatus == 'NOT PROVIDED'}
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
                        {if $accountStatus == 'CONNECTED'}
                            <img src="../modules/yowpayment/views/img/ok_icon.png" width="25" height="25"> <span class="account-status-connected">{l s="CONNECTED" mod='yowpayment'}</span>
                        {else}
                            <img src="../modules/yowpayment/views/img/milker_X_icon.svg" width="25" height="25"> <span class="account-status-not-connected">{$accountStatus}</span>
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
