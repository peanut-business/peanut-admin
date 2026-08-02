# LikeAdmin F02 authorized UI

- Browser session: same session retained for revoke and restore checks.
- Login account: `f02op26080101`.
- Recharge page URL: `http://127.0.0.1/admin/finance/recharge_record`.
- Refund page URL: `http://127.0.0.1/admin/finance/refund_record`.
- Finance menu contains both `充值记录` and `退款记录`.
- Recharge table contains `退款` buttons for paid rows.
- Refund table has three records; all rows contain `退款日志`, and the two failed records also contain `重新退款`.
- Observed authorized API requests: `recharge.recharge/lists`, `finance.refund/stat`, and `finance.refund/record` all returned HTTP 200.
- Screenshots: `authorized-recharge.png`, `authorized-refund.png`.

