<?php
/**
 * Transaction routes
 */

function handleTransactions($method, $id, $action) {
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    if ($id === 'asset' && $action) {
        getAssetTransactions($action);
    } else {
        getTransactions();
    }
}

function getTransactions() {
    $transactions = JsonDatabase::getAll('transactions');

    // Filter by days
    $days = intval($_GET['days'] ?? 30);
    $cutoff = strtotime("-{$days} days");

    $transactions = array_filter($transactions, function($tx) use ($cutoff) {
        $txTime = strtotime($tx['transaction_date'] ?? $tx['created_at']);
        return $txTime >= $cutoff;
    });

    // Sort by date descending
    usort($transactions, function($a, $b) {
        return strtotime($b['transaction_date'] ?? $b['created_at']) -
               strtotime($a['transaction_date'] ?? $a['created_at']);
    });

    // Filter by type if provided
    $type = $_GET['type'] ?? null;
    if ($type) {
        $transactions = array_filter($transactions, fn($tx) => $tx['transaction_type'] === $type);
    }

    Response::success(['transactions' => array_values($transactions)]);
}

function getAssetTransactions($assetId) {
    $transactions = JsonDatabase::getAll('transactions');

    $transactions = array_filter($transactions, fn($tx) => $tx['asset_id'] === $assetId);

    // Sort by date descending
    usort($transactions, function($a, $b) {
        return strtotime($b['transaction_date'] ?? $b['created_at']) -
               strtotime($a['transaction_date'] ?? $a['created_at']);
    });

    Response::success(['transactions' => array_values($transactions)]);
}
