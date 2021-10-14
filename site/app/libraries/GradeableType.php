<?php

namespace app\libraries;

abstract class GradeableType {
    const ELECTRONIC_FILE = 0;
    const CHECKPOINTS     = 1;
    const NUMERIC_TEXT    = 2;

    public static function typeToString(int $type): string {
        switch ($type) {
            case static::ELECTRONIC_FILE:
                return "Electronic File";
            case static::CHECKPOINTS:
                return "Checkpoints";
            case static::NUMERIC_TEXT:
                return "Numeric/Text";
            default:
                throw new \InvalidArgumentException("Invalid specified type");
        }
    }

    public static function stringToType(string $string): int {
        switch ($string) {
            case "Electronic File":
                return static::ELECTRONIC_FILE;
            case "Checkpoints":
                return static::CHECKPOINTS;
            case "Numeric":
                return static::NUMERIC_TEXT;
            default:
                throw new \InvalidArgumentException("Invalid type");
        }
    }
}
