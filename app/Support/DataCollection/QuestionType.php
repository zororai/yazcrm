<?php

namespace App\Support\DataCollection;

class QuestionType
{
    public const TEXT = 'text';
    public const LONG_TEXT = 'long_text';
    public const NUMBER = 'number';
    public const DECIMAL = 'decimal';
    public const INTEGER = 'integer';
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const TIME = 'time';
    public const SELECT_ONE = 'select_one';
    public const SELECT_MULTIPLE = 'select_multiple';
    public const YES_NO = 'yes_no';

    public const ALL = [
        self::TEXT, self::LONG_TEXT, self::NUMBER, self::DECIMAL, self::INTEGER,
        self::EMAIL, self::PHONE, self::DATE, self::DATETIME, self::TIME,
        self::SELECT_ONE, self::SELECT_MULTIPLE, self::YES_NO,
    ];

    // Types that require a non-empty `options` array in the schema.
    public const REQUIRES_OPTIONS = [self::SELECT_ONE, self::SELECT_MULTIPLE];
}
