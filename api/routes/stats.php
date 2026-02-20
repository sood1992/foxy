<?php
/**
 * Stats and reports routes
 */

function handleStats($method, $id, $action) {
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    switch ($id) {
        case 'dashboard':
            getDashboardStats();
            break;

        case 'reports':
            getReports();
            break;

        default:
            Response::error('Not found', 404);
    }
}

function getDashboardStats() {
    $assets = JsonDatabase::getAll('assets');
    $transactions = JsonDatabase::getAll('transactions');

    // Calculate counts
    $total = count($assets);
    $available = count(array_filter($assets, fn($a) => $a['status'] === 'available'));
    $checkedOut = count(array_filter($assets, fn($a) => $a['status'] === 'checked_out'));
    $maintenance = count(array_filter($assets, fn($a) => $a['status'] === 'maintenance'));
    $lost = count(array_filter($assets, fn($a) => $a['status'] === 'lost'));

    // Calculate overdue
    $now = time();
    $overdueItems = array_filter($assets, function($a) use ($now) {
        if ($a['status'] !== 'checked_out' || !$a['expected_return_date']) return false;
        return strtotime($a['expected_return_date']) < $now;
    });

    // Category breakdown
    $categories = [];
    foreach ($assets as $asset) {
        $cat = $asset['category'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = 0;
        }
        $categories[$cat]++;
    }

    $categoryBreakdown = [];
    foreach ($categories as $cat => $count) {
        $categoryBreakdown[] = ['category' => $cat, 'count' => $count];
    }
    usort($categoryBreakdown, fn($a, $b) => $b['count'] - $a['count']);

    // Recent transactions (last 30 days)
    $cutoff = strtotime('-30 days');
    $recentTransactions = array_filter($transactions, function($tx) use ($cutoff) {
        return strtotime($tx['transaction_date'] ?? $tx['created_at']) >= $cutoff;
    });

    usort($recentTransactions, function($a, $b) {
        return strtotime($b['transaction_date'] ?? $b['created_at']) -
               strtotime($a['transaction_date'] ?? $a['created_at']);
    });

    Response::success([
        'totalAssets' => $total,
        'available' => $available,
        'checkedOut' => $checkedOut,
        'maintenance' => $maintenance,
        'lost' => $lost,
        'overdue' => count($overdueItems),
        'overdueItems' => array_values($overdueItems),
        'categoryBreakdown' => $categoryBreakdown,
        'recentTransactions' => array_slice(array_values($recentTransactions), 0, 10)
    ]);
}

function getReports() {
    $days = intval($_GET['days'] ?? 30);
    $cutoff = strtotime("-{$days} days");

    $transactions = JsonDatabase::getAll('transactions');
    $assets = JsonDatabase::getAll('assets');

    // Filter to date range
    $transactions = array_filter($transactions, function($tx) use ($cutoff) {
        return strtotime($tx['transaction_date'] ?? $tx['created_at']) >= $cutoff;
    });

    // Activity by date
    $activityByDate = [];
    foreach ($transactions as $tx) {
        $date = date('M j', strtotime($tx['transaction_date'] ?? $tx['created_at']));
        if (!isset($activityByDate[$date])) {
            $activityByDate[$date] = ['date' => $date, 'checkouts' => 0, 'checkins' => 0];
        }
        if ($tx['transaction_type'] === 'checkout') {
            $activityByDate[$date]['checkouts']++;
        } else {
            $activityByDate[$date]['checkins']++;
        }
    }

    // Top borrowed assets
    $assetCounts = [];
    foreach ($transactions as $tx) {
        if ($tx['transaction_type'] === 'checkout') {
            $id = $tx['asset_id'];
            if (!isset($assetCounts[$id])) {
                $assetCounts[$id] = ['asset_id' => $id, 'asset_name' => $tx['asset_name'] ?? $id, 'count' => 0];
            }
            $assetCounts[$id]['count']++;
        }
    }
    usort($assetCounts, fn($a, $b) => $b['count'] - $a['count']);

    // Active borrowers
    $borrowers = [];
    foreach ($assets as $asset) {
        if ($asset['status'] === 'checked_out' && $asset['current_borrower']) {
            $borrowers[$asset['current_borrower']] = true;
        }
    }

    Response::success([
        'activityByDate' => array_values($activityByDate),
        'topAssets' => array_slice(array_values($assetCounts), 0, 10),
        'activeBorrowers' => count($borrowers)
    ]);
}
