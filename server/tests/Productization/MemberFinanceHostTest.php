<?php
declare(strict_types=1);

use app\common\service\MemberBalanceService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectMemberFinance(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($serverRoot);
$balanceServicePath = $serverRoot . '/app/common/service/MemberBalanceService.php';
$balanceService = (string)file_get_contents($balanceServicePath);
$accountLog = (string)file_get_contents($serverRoot . '/app/common/logic/AccountLogLogic.php');
$schema = (string)file_get_contents($serverRoot . '/database/init.sql');

expectMemberFinance(MemberBalanceService::moneyToCents('10.10') === 1010, 'decimal amount conversion changed');
expectMemberFinance(MemberBalanceService::moneyToCents(0.1) === 10, 'float amount conversion changed');
expectMemberFinance(MemberBalanceService::centsToMoney(1010) === '10.10', 'money formatting changed');
expectMemberFinance(
    str_contains($balanceService, 'MemberTenantRepository::members($context)->lock(true)'),
    'balance owner must lock the Tenant-scoped member row'
);
expectMemberFinance(str_contains($balanceService, 'AccountLogLogic::add'), 'balance owner must append a ledger row');
expectMemberFinance(!str_contains($balanceService, '$member->balance'), 'balance owner must not write a compatibility mirror');
expectMemberFinance(!str_contains($accountLog, "'after_amount' =>"), 'ledger owner must not write a compatibility mirror');
expectMemberFinance(!str_contains($schema, '`balance` DECIMAL'), 'member compatibility balance remains in the fresh schema');
expectMemberFinance(!str_contains($schema, '`after_amount` DECIMAL'), 'ledger compatibility amount remains in the fresh schema');
$memberSave = strpos($balanceService, '$member->save()');
$ledgerAppend = strpos($balanceService, 'AccountLogLogic::add');
expectMemberFinance(
    $memberSave !== false && $ledgerAppend !== false && $memberSave < $ledgerAppend,
    'ledger must record the updated balance'
);

$directWriters = [];
$balanceCallers = [];
$ledgerWriters = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($serverRoot . '/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = (string)file_get_contents($file->getPathname());
    if (preg_match('/->user_money\s*=|[\'\"]user_money[\'\"]\s*=>/', $source) === 1) {
        $directWriters[] = $file->getPathname();
    }
    if (str_contains($source, 'MemberBalanceService::applyInTransaction')) {
        $balanceCallers[] = $file->getPathname();
    }
    if (str_contains($source, 'MemberBalanceLog::create')) {
        $ledgerWriters[] = $file->getPathname();
    }
}
expectMemberFinance(
    $directWriters === [$balanceServicePath],
    'user_money must have exactly one application writer: ' . implode(', ', $directWriters)
);

$callers = [
    'app/adminapi/logic/member/MemberLogic.php',
    'app/api/logic/RechargeLogic.php',
    'app/adminapi/logic/finance/RechargeLogic.php',
];
$expectedCallerPaths = array_map(static fn(string $path): string => $serverRoot . '/' . $path, $callers);
sort($balanceCallers);
sort($expectedCallerPaths);
expectMemberFinance($balanceCallers === $expectedCallerPaths, 'balance owner caller set changed');
expectMemberFinance(
    $ledgerWriters === [$serverRoot . '/app/common/logic/AccountLogLogic.php'],
    'member balance ledger must have exactly one writer'
);
foreach ($callers as $relativePath) {
    $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
    $call = strpos($source, 'MemberBalanceService::applyInTransaction');
    expectMemberFinance($call !== false, 'balance path bypasses the unique owner: ' . $relativePath);
    expectMemberFinance(strrpos(substr($source, 0, $call), 'Db::startTrans()') !== false, 'balance path lacks an outer transaction: ' . $relativePath);
    expectMemberFinance(strpos($source, 'Db::commit()', $call) !== false, 'balance path lacks an atomic commit: ' . $relativePath);
    expectMemberFinance(!str_contains($source, 'AccountLogLogic::add'), 'caller writes the ledger directly: ' . $relativePath);
}

$settle = (string)file_get_contents($serverRoot . '/app/api/logic/RechargeLogic.php');
$paidGuard = strpos($settle, 'pay_status === RechargeOrder::PAY_STATUS_PAID');
$credit = strpos($settle, 'MemberBalanceService::applyInTransaction');
expectMemberFinance($paidGuard !== false && $credit !== false && $paidGuard < $credit, 'paid callback guard must precede credit');
expectMemberFinance(strpos($settle, "where('sn', \$orderSn)->lock(true)") < $paidGuard, 'recharge order must be locked before the paid guard');

$refund = (string)file_get_contents($serverRoot . '/app/adminapi/logic/finance/RechargeLogic.php');
$retryStart = strpos($refund, 'public static function refundAgain');
$retryEnd = strpos($refund, 'private static function retryLockName', $retryStart ?: 0);
expectMemberFinance($retryStart !== false && $retryEnd !== false, 'refund retry boundary is missing');
expectMemberFinance(
    !str_contains(substr($refund, $retryStart, $retryEnd - $retryStart), 'MemberBalanceService::applyInTransaction'),
    'refund retry must not deduct the balance again'
);

$paymentMigration = (string)file_get_contents(
    $serverRoot . '/database/init.sql'
);
$refundMigration = (string)file_get_contents(
    $serverRoot . '/database/migrations/20260820-recharge-partial-refund.sql'
);
expectMemberFinance(str_contains($paymentMigration, 'uk_transaction_id'), 'transaction id unique guard is missing');
expectMemberFinance(
    str_contains($refundMigration, 'idx_refund_record_tenant_order_amount')
        && str_contains($refundMigration, 'DROP INDEX `uk_refund_record_tenant_order`')
        && str_contains($refundMigration, 'DROP INDEX `uk_refund_record_order_global`'),
    'partial-refund cumulative lookup schema is missing'
);

$rechargeEvidence = json_decode((string)file_get_contents(
    $repositoryRoot . '/output/playwright/s01/recharge-payment-summary.json'
), true, 512, JSON_THROW_ON_ERROR);
expectMemberFinance(($rechargeEvidence['status'] ?? '') === 'passed', 'sealed recharge evidence is not passed');
expectMemberFinance(($rechargeEvidence['checks']['balance_credited_once'] ?? false) === true, 'sealed recharge evidence lacks single credit');
expectMemberFinance(($rechargeEvidence['checks']['duplicate_callback_idempotent'] ?? false) === true, 'sealed recharge evidence lacks callback idempotency');
expectMemberFinance(($rechargeEvidence['checks']['account_log_count'] ?? 0) === 1, 'sealed recharge evidence has duplicate ledger rows');
expectMemberFinance(($rechargeEvidence['fixtures_cleaned'] ?? false) === true, 'sealed recharge fixtures were not cleaned');

$refundEvidence = json_decode((string)file_get_contents(
    $repositoryRoot . '/output/playwright/f02/f02-15-audit.json'
), true, 512, JSON_THROW_ON_ERROR);
expectMemberFinance(($refundEvidence['all_assertions_pass'] ?? false) === true, 'sealed refund audit is not passed');
expectMemberFinance(
    ($refundEvidence['assertions']['one_refund_record_per_order']['pass'] ?? false) === true,
    'sealed refund audit lacks one-record-per-order proof'
);
expectMemberFinance(
    ($refundEvidence['assertions']['one_101_log_at_most_per_order']['pass'] ?? false) === true,
    'sealed refund audit lacks one-deduction-per-order proof'
);

expectMemberFinance(!str_contains($balanceService, 'PeanutAdmin\\'), 'application balance owner must not deep import core');

echo "PB05-MEMBER-FINANCE-001 passed\n";
