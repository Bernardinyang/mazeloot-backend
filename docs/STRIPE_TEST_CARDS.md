# Stripe Test Cards

Use these with **test API keys** (`pk_test_*`, `sk_test_*`). For all cards: use any future expiry (e.g. `12/34`), any 3-digit CVC (4 for Amex), any postal code.

Source: [Stripe Testing Docs](https://docs.stripe.com/testing#cards)

---

## Successful payments (by card brand)

| Brand | Number | CVC | Date |
|-------|--------|-----|------|
| Visa | 4242 4242 4242 4242 | Any 3 digits | Any future date |
| Visa (debit) | 4000 0566 5566 5556 | Any 3 digits | Any future date |
| Mastercard | 5555 5555 5555 4444 | Any 3 digits | Any future date |
| Mastercard (2-series) | 2223 0031 2200 3222 | Any 3 digits | Any future date |
| Mastercard (debit) | 5200 8282 8282 8210 | Any 3 digits | Any future date |
| Mastercard (prepaid) | 5105 1051 0510 5100 | Any 3 digits | Any future date |
| American Express | 3782 8224 6310 005 | Any 4 digits | Any future date |
| American Express | 3714 4963 5398 431 | Any 4 digits | Any future date |
| Discover | 6011 1111 1111 1117 | Any 3 digits | Any future date |
| Discover | 6011 0009 9013 9424 | Any 3 digits | Any future date |
| Discover (debit) | 6011 9811 1111 1113 | Any 3 digits | Any future date |
| Diners Club | 3056 9300 0902 0004 | Any 3 digits | Any future date |
| Diners Club (14-digit) | 3622 7206 2716 67 | Any 3 digits | Any future date |
| BCcard / DinaCard | 6555 9000 0060 4105 | Any 3 digits | Any future date |
| JCB | 3566 0020 2036 0505 | Any 3 digits | Any future date |
| UnionPay | 6200 0000 0000 005 | Any 3 digits | Any future date |
| UnionPay (debit) | 6200 0000 0000 047 | Any 3 digits | Any future date |
| UnionPay (19-digit) | 6205 5000 0000 0000 04 | Any 3 digits | Any future date |

### Co-branded

| Brand/Co-brand | Number |
|----------------|--------|
| Cartes Bancaires/Visa | 4000 0025 0000 1001 |
| Cartes Bancaires/Mastercard | 5555 5525 0000 1001 |
| eftpos Australia/Visa | 4000 0503 6000 0001 |
| eftpos Australia/Mastercard | 5555 0503 6000 0080 |

---

## Successful payments (by country)

| Country | Number | Brand |
|---------|--------|-------|
| United States | 4242 4242 4242 4242 | Visa |
| Argentina | 4000 0003 2000 0021 | Visa |
| Brazil | 4000 0007 6000 0002 | Visa |
| Canada | 4000 0012 4000 0000 | Visa |
| Mexico | 4000 0484 0008 001 | Visa |
| Mexico | 5062 2100 0000 0009 | Carnet |
| United Kingdom | 4000 0826 0000 0000 | Visa |
| United Kingdom (debit) | 4000 0582 6000 0005 | Visa |
| United Kingdom | 5555 5582 6555 4449 | Mastercard |
| France | 4000 0025 0000 0003 | Visa |
| Germany | 4000 0276 0000 0016 | Visa |
| Spain | 4000 0724 0000 0007 | Visa |
| Australia | 4000 0036 0000 0006 | Visa |
| China | 4000 0156 0000 0002 | Visa |
| India | 4000 0356 0000 0008 | Visa |
| Japan | 4000 0392 0000 0003 | Visa |
| Japan | 3530 1113 3330 0000 | JCB |
| Singapore | 4000 0702 0000 0003 | Visa |

---

## HSA / FSA

| Brand/Type | Number |
|------------|--------|
| Visa FSA | 4000 0512 3000 0072 |
| Visa HSA | 4000 0512 3000 0072 |
| Mastercard FSA | 5200 8282 8282 8897 |

---

## Declined payments

| Description | Number | Error code |
|-------------|--------|------------|
| Generic decline | 4000 0000 0000 0002 | card_declined / generic_decline |
| Insufficient funds | 4000 0000 0000 9995 | card_declined / insufficient_funds |
| Lost card | 4000 0000 0000 9987 | card_declined / lost_card |
| Stolen card | 4000 0000 0000 9979 | card_declined / stolen_card |
| Expired card | 4000 0000 0000 0069 | expired_card |
| Incorrect CVC | 4000 0000 0000 0127 | incorrect_cvc |
| Processing error | 4000 0000 0000 0119 | processing_error |
| Incorrect number | 4242 4242 4242 4241 | incorrect_number |
| Velocity limit exceeded | 4000 0000 0000 6975 | card_velocity_exceeded |
| Decline after attaching | 4000 0000 0000 0341 | Attach succeeds, charge fails |

---

## Fraud prevention (Radar)

| Description | Number | Details |
|-------------|--------|---------|
| Always blocked | 4100 0000 0000 0019 | Highest risk, Radar always blocks |
| Highest risk | 4000 0000 0000 4954 | Radar may block |
| Elevated risk | 4000 0000 0000 9235 | May be queued for review |
| CVC check fails | 4000 0000 0000 0101 | Provide CVC to trigger |
| Postal code check fails | 4000 0000 0000 0036 | Provide postal code to trigger |
| CVC + elevated risk | 4000 0584 0030 7872 | Provide CVC |
| Postal + elevated risk | 4000 0584 0030 6072 | Provide postal code |
| Line1 check fails | 4000 0000 0000 0028 | Address line 1 fails |
| Address checks fail | 4000 0000 0000 0010 | Postal + line 1 fail |
| Address unavailable | 4000 0000 0000 0044 | Checks unavailable |

---

## Disputes

| Description | Number | Details |
|-------------|--------|---------|
| Fraudulent | 4000 0000 0000 0259 | Disputed as fraudulent |
| Not received | 4000 0000 0000 2685 | Product not received |
| Inquiry | 4000 0000 0000 1976 | Inquiry |
| Early fraud warning | 4000 0000 0000 5423 | Early fraud warning |
| Multiple disputes | 4000 0040 4000 0079 | Multiple disputes |

**Evidence (submit as `uncategorized_text`):** `winning_evidence`, `losing_evidence`, `escalate_inquiry_evidence`

---

## Asynchronous refunds

| Description | Number | Details |
|-------------|--------|---------|
| Async success | 4000 0000 0000 7726 | Refund starts pending, then succeeds |
| Async failure | 4000 0000 0000 5126 | Refund succeeds, then fails |

---

## Bypass pending balance

| Description | Number |
|-------------|--------|
| US charge | 4000 0000 0000 0077 |
| International charge | 4000 0037 2000 0278 |

---

## 3D Secure authentication

| Description | Number | Details |
|-------------|--------|---------|
| Auth unless set up | 4000 0025 0000 3155 | Off-session needs setup |
| Always authenticate | 4000 0276 0000 3184 | Always requires auth |
| Already set up | 4000 0380 0000 0446 | Off-session works without auth |
| Insufficient funds | 4000 0826 0000 3178 | Declined after auth |
| 3DS Required – OK | 4000 0000 0000 3220 | Must complete 3DS |
| 3DS Required – Declined | 4000 0840 0000 1629 | Declined after 3DS |
| 3DS Required – Error | 4000 0840 0000 1280 | 3DS lookup fails |
| 3DS Supported – OK | 4000 0000 0000 3055 | 3DS optional |
| 3DS Supported – Error | 4000 0000 0000 3097 | 3DS optional, lookup error |
| 3DS Unenrolled | 4242 4242 4242 4242 | 3DS supported but not enrolled |
| 3DS Not supported | 3782 8224 6310 005 | Amex, no 3DS |

---

## Captcha challenge

| Number | Details |
|--------|---------|
| 4000 0000 0000 1208 | Succeeds if captcha answered correctly |
| 4000 0000 0000 3725 | Succeeds if captcha answered correctly |

---

## Most common for quick testing

| Scenario | Number |
|----------|--------|
| **Success (Visa)** | 4242 4242 4242 4242 |
| **Declined** | 4000 0000 0000 0002 |
| **3D Secure required** | 4000 0025 0000 3155 |
