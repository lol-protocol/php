<?php

namespace PhoneDirectory;

final class DatabaseConstants
{
    public const DEFAULT_COUNTRY_CODE = 'US';
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const SQL_LIMIT_ONE = ' LIMIT 1';
    public const SQL_ORDER_BY = ' ORDER BY ';
    public const SQL_WHERE = ' WHERE ';
    public const SQL_AND = ' AND ';

    public const QUERY_LIMIT_ONE_BY_ID = 'SELECT * FROM %s WHERE id = :id LIMIT 1';
    public const QUERY_DELETE_BY_ID = 'DELETE FROM %s WHERE id = :id';
    public const QUERY_COUNT = 'SELECT COUNT(*) as count FROM %s';
    public const QUERY_GET_ALL = 'SELECT * FROM %s ORDER BY %s';

    public const SQL_BACKFILL_NOT_NULL_ERROR = 'Cannot query %s for backfill: %s';
    public const ERROR_BACKFILL_TRANSACTION_FAILED = 'Backfill transaction failed: %s';
    public const ERROR_BATCH_INSERT_FAILED = 'Batch insert failed: %s';
    public const ERROR_UPDATE_WITHOUT_ID = 'Cannot update entity without ID';
    public const ERROR_NO_LAST_INSERT_ID = 'Failed to get last insert ID from database';

    public const MIGRATION_NULL_COUNTRY_CODE = "UPDATE %s SET country_code = '%s' WHERE country_code IS NULL";
    public const BACKFILL_CONDITION = ' IS NULL';
}
