<?php
/**
 * JSON-based database for FOXY Inventory System
 */

class JsonDatabase {
    private static $cache = [];

    /**
     * Get all records from a collection
     */
    public static function getAll($collection) {
        $file = DATA_PATH . "/{$collection}.json";

        if (isset(self::$cache[$collection])) {
            return self::$cache[$collection];
        }

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true) ?: [];
        self::$cache[$collection] = $data;

        return $data;
    }

    /**
     * Save all records to a collection
     */
    public static function saveAll($collection, $data) {
        $file = DATA_PATH . "/{$collection}.json";
        self::$cache[$collection] = $data;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        return true;
    }

    /**
     * Find a record by field value
     */
    public static function find($collection, $field, $value) {
        $data = self::getAll($collection);
        foreach ($data as $record) {
            if (isset($record[$field]) && $record[$field] == $value) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Find record by ID
     */
    public static function findById($collection, $id) {
        return self::find($collection, 'id', $id);
    }

    /**
     * Insert a new record
     */
    public static function insert($collection, $record) {
        $data = self::getAll($collection);

        // Generate ID if not provided
        if (!isset($record['id'])) {
            $maxId = 0;
            foreach ($data as $item) {
                if (isset($item['id']) && is_numeric($item['id']) && $item['id'] > $maxId) {
                    $maxId = $item['id'];
                }
            }
            $record['id'] = $maxId + 1;
        }

        // Add timestamps
        $record['created_at'] = $record['created_at'] ?? date('Y-m-d H:i:s');
        $record['updated_at'] = date('Y-m-d H:i:s');

        $data[] = $record;
        self::saveAll($collection, $data);

        return $record;
    }

    /**
     * Update a record by field
     */
    public static function update($collection, $field, $value, $updates) {
        $data = self::getAll($collection);
        $updated = false;

        foreach ($data as &$record) {
            if (isset($record[$field]) && $record[$field] == $value) {
                $record = array_merge($record, $updates);
                $record['updated_at'] = date('Y-m-d H:i:s');
                $updated = $record;
                break;
            }
        }

        if ($updated) {
            self::saveAll($collection, $data);
        }

        return $updated;
    }

    /**
     * Update by ID
     */
    public static function updateById($collection, $id, $updates) {
        return self::update($collection, 'id', $id, $updates);
    }

    /**
     * Delete a record by field
     */
    public static function delete($collection, $field, $value) {
        $data = self::getAll($collection);
        $initialCount = count($data);

        $data = array_filter($data, function($record) use ($field, $value) {
            return !isset($record[$field]) || $record[$field] != $value;
        });

        $data = array_values($data); // Re-index array

        if (count($data) < $initialCount) {
            self::saveAll($collection, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete by ID
     */
    public static function deleteById($collection, $id) {
        return self::delete($collection, 'id', $id);
    }

    /**
     * Filter records
     */
    public static function filter($collection, $filters) {
        $data = self::getAll($collection);

        return array_filter($data, function($record) use ($filters) {
            foreach ($filters as $field => $value) {
                if (!isset($record[$field])) return false;
                if ($value !== null && $record[$field] != $value) return false;
            }
            return true;
        });
    }

    /**
     * Count records
     */
    public static function count($collection, $filters = []) {
        if (empty($filters)) {
            return count(self::getAll($collection));
        }
        return count(self::filter($collection, $filters));
    }

    /**
     * Clear cache
     */
    public static function clearCache($collection = null) {
        if ($collection) {
            unset(self::$cache[$collection]);
        } else {
            self::$cache = [];
        }
    }
}
