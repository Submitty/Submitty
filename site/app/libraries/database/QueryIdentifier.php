<?php

declare(strict_types=1);

namespace app\libraries\database;

class QueryIdentifier {
    public const SELECT = 'select';
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const DELETE = 'delete';
    public const UNKNOWN = 'unknown';

    public static function identify(string $query): string {
        $query = strtolower(trim($query));
        if (str_starts_with($query, 'with')) {
            $tokens = str_split($query);
            $pos = 4;
            $tokenCount = count($tokens);
            $parenthesis = 0;
            $afterColumnList = false;
            $afterCte = false;
            while ($pos < $tokenCount) {
                if ($tokens[$pos] === ' ') {
                    $pos++;
                    continue;
                }

                if ($afterColumnList) {
                    if ($tokens[$pos] === '(') {
                        $parenthesis++;
                    }
                    elseif ($tokens[$pos] === ')') {
                        $parenthesis--;
                        if ($afterColumnList && $parenthesis === 0) {
                            $afterCte = true;
                        }
                    }
                    elseif ($afterCte && $tokens[$pos] === ',' && $afterCte) {
                        $afterCte = false;
                        $afterColumnList = false;
                    }
                }

                $token = '';
                while ($pos < $tokenCount && preg_match("/[a-zA-Z0-9\._]/", $tokens[$pos])) {
                    $token .= $tokens[$pos];
                    $pos++;
                }

                if ($token !== '') {
                    if ($parenthesis === 0 && $token === 'as') {
                        $afterColumnList = true;
                    }
                    elseif ($afterColumnList && $afterCte) {
                        $pos -= strlen($token);
                        break;
                    }
                }
                else {
                    $pos++;
                }
            }
            $query = implode("", array_slice($tokens, $pos));
        }

        if (str_starts_with($query, QueryIdentifier::SELECT)) {
            return QueryIdentifier::SELECT;
        }
        elseif (str_starts_with($query, QueryIdentifier::UPDATE)) {
            return QueryIdentifier::UPDATE;
        }
        elseif (str_starts_with($query, QueryIdentifier::INSERT)) {
            return QueryIdentifier::INSERT;
        }
        elseif (str_starts_with($query, QueryIdentifier::DELETE)) {
            return QueryIdentifier::DELETE;
        }
        return QueryIdentifier::UNKNOWN;
    }
}
