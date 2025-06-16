## Changelog

###  2.3.9 - 2025-06-16
* Widget fully integrated 
* Allow error messages personalization
* Fix refund loop on hook hookActionOrderStatusUpdate
* Clean removing displayAdminOrder hook

#### Environment (Development, QA validation)
* Prestashop version: 8.2.1


###  2.3.8 - 2024-04-22
  * Add reset/refund on order state error
  * Upgrade api version from 26 to 34



###  2.3.7 - 2024-03-03
  * Add floa payment method
  * Fix upgrade script for refund
  * Fix partial refund with shipping fee
  * Add display.rule.param for smartdisplay

###  2.3.6 - 2024-11-07
  * Add Klarna payment compatibility
  * Update SDK dependency to fit rebranding monext/monext-php
  * Simplify secondary contracts configuration
  * Update logos
  * Add log visibility in backoffice

###  2.3.5 - 2023-05-10
  * Partial refund
  * Normalize REC payment

###  2.3.4 - 2023-04-11
  * Total refund on status modification
  * Product select in configuration (payment REC)

###  2.3.3 - 2024-03-27
  * Update payment logos

###  2.3.2 - 2024-02-13
  * Reset transaction on order cancel
  * Prevent use of payline in cart if no contract defined

###  2.3.1 - 2023-09-12
  * Fix compatibility php 8.x
  * Downgrade composer requirements
  * Upgrade Payline SDK from v4.73 to v4.75

###  2.3.0 - 2023-04-04
  * Compatibility with prestashop 8.0.x

###  2.2.13 - 2022-10-31
  * Fix TRD (send amounts with taxes)

###  2.2.12 - 2022-07-22
  * Fix configuration with only one point of sell

###  2.2.11 - 2022-05-06
  * Use PaylineSDK v4.69
  * Set API version to 26

###  2.2.10 - 2022-01-13
  * Fix contract import (when only one by point of sell)
  * Fix default category


###  2.2.9 - 2021-10-08
  * Add default category in configuration

###  2.2.8 - 2021-05-14
  * Fix refund
  * Fix Payline admin panel with multistore configuration
  * Fix error Street max 100 char (cf : <a href="https://github.com/PaylineByMonext/payline-prestashop/issues/5">Adress >100 characters no error logged</a> and <a href="https://docs.payline.com/display/DT/Object+-+address">Doc Payline: Object - address</a>)
  * Replace hook actionObjectOrderSlipAddAfter by actionObjectOrderSlipAddBefore
  * Set API version to 21

###  2.2.7 - 2020-08-14
  * Fix translation.

###  2.2.6 - 2020-03-06
  * Update properly order.total_paid_real on partial refund.
  * Correct french translation backoffice "alternative contracts"
  * Backoffice : delete need help block

###  2.2.5 - 2019-04-04
  * Use PaylineSDK v4.59

###  2.2.4 - 2018-11-12
  * Add details in README. No functional nor technical changes.

###  2.2.3 - 2018-10-05
  * Add prerequisites in README. No functional nor technical changes.

###  2.2.2 - 2018-08-03
  * remove auto-refund when an amount mismatch is detected

###  2.2.1 - 2018-04-09
  * fix 'title' notice
  * allow guest order with in-shop UX
  * disable auto-capture for already captured payments

###  2.2 - 2018-01-20
  * recurring payment method

###  2.1 - 2018-01-05
  * nx payment method

###  2.0 - 2017-12-08
  * Simple payment method
  * immediate or differed payment capture (triggered by order status change)
  * compliant with all payment means
  * redirect to payment page or use in-site secure payment form

