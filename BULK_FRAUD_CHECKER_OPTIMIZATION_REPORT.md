# Bulk Fraud Checker optimization report

## 1. Executive Summary

Checking one phone number was slow because `FraudCheckService::getReport()` called Steadfast, Pathao, Paperfly, RedX, and Carrybee one after another. Those five lookups do not use each other’s results. The merchant bulk page then repeats that whole sequence once per number.

The change runs those courier calls together when a saved session or API key is already available, and uses the existing `check()` path whenever a pooled response is missing, rejected, or not usable. Aggregation, the daily result cache, the per-merchant lock, and the one-number `next` request are unchanged.

On one warm live number, sequential `getReport()` took 1479 ms and 2408 ms. The pooled call took 483 ms and returned the same totals. One earlier pooled call took 52563 ms and still returned those same totals. Batches of 10 through 500 numbers were not sent to the live providers.

## 2. Root Cause Analysis

A bulk batch does not send 500 numbers in one provider request. `resources/js/Pages/Portal/FraudCheck/Batch.vue` `runPending()` posts `portal.fraud-check.batches.next` once per pending row, with a 180 second timeout. `MerchantBulkFraudCheck::advance()` claims one row. `guardedResolve()` holds `courier-history-merchant:{id}` so only one check for that merchant is in flight. That lock is what keeps the daily quota from being passed by a second overlapping request. It was left in place.

Inside the single-number check, `FraudCheckService::getReport()` waited for each courier before starting the next. Steadfast, Pathao, Paperfly, RedX, and Carrybee are independent until `CourierReportFormatter::aggregateTotals()`. Same-day repeats are already served from `BulkFraudDailyResult` and do not call the couriers again. Duplicate numbers are removed when the batch is parsed.

This is not an N+1 query problem, and the results table is not the delay. The page replaces one row when each `next` response arrives. Laravel’s process concurrency driver was not used: it starts a separate PHP process per task, and the fork driver is not available for web requests. `Http::pool` is the same mechanism the courier status pollers already use.

Provider behavior that shaped the fix:

- A Pathao request inside the pool was reset by the peer (`cURL error 56`) until that request forced HTTP/1.1 and IPv4, which the other courier clients already do. After that, an isolated Pathao pool call returned HTTP 200 in 444 ms with the same delivery total the sequential check had returned.
- An empty or `is_new` Pathao merchant payload is not treated as the final result. `check()` still tries the Hermes fallback in that case, so the pool returns that payload to the fallback instead of keeping zeros.
- RedX on the measured number answered HTTP 400 on both the sequential and pooled paths. That stays an unavailable result, not a zero history.

## 3. Before vs After Benchmarks

Live batches of more than one number were not run. The table below is only the measured single-number `getReport()` times, plus the local overlap check that does not call a courier.

| Batch Size | Before | After | Improvement | Data Accuracy |
|---|---|---|---|---|
| 1 | 1479 ms and 2408 ms warm sequential, correct Pathao counts. Cold sequential 71950 ms with Pathao counts missing. | 483 ms warm pooled. One pooled call was 52563 ms with the same correct totals. | Warm: 1479 ms to 483 ms (996 ms, 3.1×) and 2408 ms to 483 ms (1925 ms, 5.0×). The 52563 ms call is not an improvement. | Verified on this number |
| 10 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 50 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 100 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 250 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 400 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 500 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |
| 1,000 | NOT MEASURED | NOT MEASURED | NOT MEASURED | Blocked |

The warm sequential and warm pooled fingerprints matched: total 26162, confirmed 21943, cancel 4219, success rate 84%. Steadfast 169/59/110, Pathao 25883/21823/4060, Paperfly 80/44/36, RedX unavailable, Carrybee 30/17/13. The 483 ms call kept all five pooled responses (Steadfast, Pathao, Paperfly, and Carrybee HTTP 200; RedX HTTP 400) and the pool itself was 460 ms.

A local pair of HTTP listeners, each delaying 400 ms, took 827 ms sequentially and 403 ms with `Http::pool`. That confirms the pool overlaps. It is not a courier measurement.

A projection from the warm per-number times, not a measured batch: 500 cache misses at 1479–2408 ms is about 12–20 minutes, and at 483 ms is about 4 minutes, only if every provider keeps answering the way this one warm number did and does not rate-limit. Same-day numbers already skip the live calls.

## 4. Changes Implemented

`FraudCheckService::fetchCourierReports()` builds one pool for the couriers that already have a session or API key. Any courier left out, or whose pooled response is not a usable report, still runs `check()`.

- `config/fraud_check.php`: `parallel_couriers`, default true. Set `FRAUD_CHECK_PARALLEL_COURIERS=false` to restore the sequential calls.
- `SteadfastFraudChecker::poolPlan()`: official score request for the first known key pair. An unusable response falls back, so later keys and the portal login still run.
- `PathaoFraudChecker::poolPlan()`: cached merchant token, HTTP/1.1, IPv4. The pooled report is kept only when it has delivery counts. Empty, `is_new`, 401, and connection failures fall back to `check()`, which still tries other tokens and Hermes.
- `PaperflyFraudChecker`, `RedxFraudChecker`, `CarrybeeFraudChecker`: cached-token calls only. Login stays on `check()`. HTTP 401 falls back. RedX’s sample payload and Carrybee’s missing customer stay distinct from a verified zero.

No migration. No change to quota accounting, phone normalization, risk formulas, merchant isolation, or the batch UI.

## 5. Data Integrity Verification

The live warm sequential report and the live warm pooled report matched number for number, including RedX staying unavailable. A zero, an unavailable RedX response, and a failed request were not collapsed into one another.

`tests/Feature/ParallelCourierFetchTest.php` feeds delayed fixture responses for Steadfast (8 delivered, 2 cancelled) and Pathao (5 successful, 1 failed). Both modes aggregate to total 16, confirmed 13, cancel 3, and the pooled mode records one request to each provider. A second test returns Pathao `is_new` from the pool and real counts from the fallback `check()`. The result keeps the fallback counts (the pool’s empty payload is discarded) and records two Pathao requests.

Existing checker tests still cover login fallback, HTTP 401 refresh, RedX’s sample payload, Carrybee’s missing customer, and the bulk batch rules (quota, duplicate removal, same-day reuse, merchant isolation, CSV).

## 6. Regression Test Report

Command: `php artisan test --filter='ParallelCourierFetchTest|FraudCheckServiceTest|MerchantBulkFraudCheckTest|PathaoFraudCheckerTest|PaperflyFraudCheckerTest|RedxFraudCheckerTest|CarrybeeFraudCheckerTest|SteadfastFraudApiCredentialTest'`

- Passed: 77
- Failed: 0
- Skipped: 0
- Assertions: 573

PHPUnit printed doc-comment metadata warnings for unrelated plugin and payment test classes. Those warnings were already present and those classes were not part of this run’s failures.

Remaining risks: a pooled call can still sit until the courier timeout (up to 30 seconds, 12 seconds for Steadfast) and then run `check()`. That is the likely shape of the 52563 ms call. Provider rate limits are unchanged. The first number in a cold session still logs in through `check()` before later numbers can use the pool.

## 7. Production Safety

The response shape is the same. Merchants still submit one number per `next` request. Another merchant’s batch is still unreadable. The daily cache and the merchant lock are still what they were.

Rollback: set `FRAUD_CHECK_PARALLEL_COURIERS=false` and reload config (`php artisan config:cache` if production caches config). No migration to reverse.

Watch the first real batches for courier HTTP 429s and for single numbers that take tens of seconds. Those are provider or login stalls, not a sign that the totals were invented. Do not turn this into 500 simultaneous provider calls. The pool size is the number of couriers for one phone, which is five.

This change is not deployed.

## 8. Final Verdict

**CONDITIONALLY READY**

The warm single-number path is faster and matched the sequential courier totals. The fallback rules and the existing fraud tests passed. Ship it behind `FRAUD_CHECK_PARALLEL_COURIERS`, which already defaults to on and can be turned off without a deploy rollback of the code.

It is not an unconditional production sign-off. Batches of 400–500 numbers were not run against the live providers, and one pooled call in this session took 52563 ms before the warm 483 ms call. Watch the first merchant batches after release.
