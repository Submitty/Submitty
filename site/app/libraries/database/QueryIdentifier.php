<?php

declare(strict_types=1);

namespace app\libraries\database;

use app\libraries\Utils;

class QueryIdentifier {
    const SELECT = 'select';
    const INSERT = 'insert';
    const UPDATE = 'update';
    const DELETE = 'delete';
    const UNKNOWN = 'unknown';

    public static function identify(string $query): string {
        $query = strtolower(trim($query));
        if (Utils::startsWith($query, 'with')) {
            $tokens = str_split($query);
            $pos = 4;
            $tokenCount = count($tokens);
            $paranthesis = 0;
            $afterColumnList = false;
            $afterCte = false;
            while ($pos < $tokenCount) {
                if ($tokens[$pos] === ' ') {
                    $pos++;
                    continue;
                }

                if ($afterColumnList) {
                    if ($tokens[$pos] === '(') {
                        $paranthesis++;
                    }
                    elseif ($tokens[$pos] === ')') {
                        $paranthesis--;
                        if ($afterColumnList && $paranthesis === 0) {
                            $afterCte = true;
                        }
                    }
                    elseif ($afterCte && $tokens[$pos] === ',' && $afterCte) {
                        $afterCte = false;
                        $afterColumnList = false;
                    }
                }

                $token = '';
                while (preg_match("/[a-zA-Z0-9\._]/", $tokens[$pos])) {
                    $token .= $tokens[$pos];
                    $pos++;
                }

                if ($token !== '') {
                    if ($paranthesis === 0 && $token === 'as') {
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

        if (Utils::startsWith($query, QueryIdentifier::SELECT)) {
            return QueryIdentifier::SELECT;
        }
        elseif (Utils::startsWith($query, QueryIdentifier::UPDATE)) {
            return QueryIdentifier::UPDATE;
        }
        elseif (Utils::startsWith($query, QueryIdentifier::INSERT)) {
            return QueryIdentifier::INSERT;
        }
        elseif (Utils::startsWith($query, QueryIdentifier::DELETE)) {
            return QueryIdentifier::DELETE;
        }
        return QueryIdentifier::UNKNOWN;
    }
}
